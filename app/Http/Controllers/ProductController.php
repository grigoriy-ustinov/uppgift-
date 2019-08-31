<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Product;
use App\Http\Resources\ProductCollection;
use Illuminate\Support\Collection; 
class ProductController extends Controller
{
    public function show($pageNr, $pageSize)
    {
        $data = $this->getData();
        $products = $data[0];
        $attributes = $data[1];

        $products = collect($products);
        $attributes = collect($attributes);
        
        $itemCount = $products->count();
        $pages = $itemCount / $pageSize;
        $pages = ceil($pages);
        
        $mergedData = $this->mergeAttributes($products, $attributes);

        $mergedData = collect($mergedData);


        $mergedData = $mergedData->sortBy('name');

        $requestedChunk = [];
        $requestedChunk['products'] = $mergedData->forPage($pageNr, $pageSize)->toArray();
    
        $requestedChunk['page'] = $pageNr;
        $requestedChunk['totalPages'] = (int)$pages;
        $requestedChunk = collect($requestedChunk);
        
        $requestedChunk = json_encode($requestedChunk);
        dump($requestedChunk);

    }
    public function getData()//collectiing data
    {
        $productsData = file_get_contents('http://draft.grebban.com/backend/products.json');
        $attributesData = file_get_contents('http://draft.grebban.com/backend/attribute_meta.json');
        $productsData = json_decode($productsData);
        $attributesData = json_decode($attributesData);
        if(!empty($productsData) && !empty($attributesData))
        {
            return [$productsData, $attributesData];
        }
        else{
            echo 'rip';
            die();
        }
    }
    public function mergeAttributes($products, $attributes)
    {
        
        $products = collect($products);
        $metaAttribute = collect($attributes);
        $finalProducts = [];
        $arrayToReplace = [];

        $products->each(function($product, $key) use ($metaAttribute, $arrayToReplace, &$finalProducts){
            $product->attributes = (array) $product->attributes;
            $arrayToReplace = array();

            foreach($product->attributes as $productAttributeName => $value){ //Loop through all product attributes 

                $arrayToReplace = $this->getSingleAttribute($metaAttribute, $productAttributeName, $value, $arrayToReplace);
            }
            //$arrayToReplace = json_encode($arrayToReplace);
            //dump($arrayToReplace);
            $product->attributes = &$arrayToReplace;
            array_push($finalProducts, $product);
        });

        return $finalProducts;
    }


    public function getSingleAttribute($metaAttribute, $productAttributeName, $productAttribute, $arrayToReplace)
    {
        foreach($metaAttribute as $key => $value)
        {
            if($value->code  == $productAttributeName)//checks if product data matches attribute data 
            {
                $arrayToReplace = $this->parseDoubleAttributes($value, $productAttribute, $arrayToReplace);
            }
        }
        return $arrayToReplace;
    }
    public function parseDoubleAttributes($value,$productAttribute, $arrayToReplace)
    {
        //echo $value->code . ' should be equal ' . $productAttributeName . ' <br>';
        if(strpos($productAttribute, ','))//parse keys with multiple values
        {
            foreach(explode(',', $productAttribute) as $separetedValue)
            {
                //$this->findSubCategories($separetedValue);
                foreach($value->values as $valueList)
                {
                    //echo '_______ comparing '. $separetedValue . ' with '. $valueList->name. '________ <br>';
                    if($separetedValue == $valueList->code)
                    {
                        $formatedString = $this->findSubCategories($separetedValue, $value);
                        if(empty($formatedString))
                        {
                            array_push($arrayToReplace, ['name' => $value->name, 'value' => $valueList->name]);
                        }
                        else
                        {
                            array_push($arrayToReplace, ['name' => $value->name, 'value' => $formatedString]);
                        }
                        //echo 'attibute name '. $valueList->name . ' with value ' . $separetedValue. ' <br>';
                    }
                }
            }
        }
        else
        {
            foreach($value->values as $valueList)
            {
                //echo $productAttributeName . ' comparing with ' . $valueList->code. '<br>';
                if($productAttribute == $valueList->code)
                {
                    $formatedString = $this->findSubCategories($productAttribute, $value);
                    if(empty($formatedString))
                    {
                        array_push($arrayToReplace, ['name' => $value->name, 'value' => $valueList->name]);
                    }
                    else
                    {
                        array_push($arrayToReplace, ['name' => $value->name, 'value' => $formatedString]);
                    }
                }
            }
        }
        return $arrayToReplace;
    }

    public function findSubCategories($word, $values)
    {
        //[A-z]*[_][0-9]*
        ///[A-z]*?_[0-9]*/
        $stringToReturn = '';
        if(preg_match_all('/[A-z]*[_][0-9]*/', $word, $array))
        {
            if(isset($array['0']['1']))
            {
                $category = '';
                $stringToReturn = '';
                foreach($array['0'] as $subCategory)
                {
                    $category .= $subCategory;
                    //echo 'Subcategory code: ' .$subCategory . '<br>';
                    //echo 'Category code: ' .$category . '<br>';
                    foreach($values->values as $value)
                    {
                        //dump($value);
                        //echo $category;
                        if($category == $value->code)
                        {
                            $stringToReturn .= $value->name . ' > ';
                            //echo 'String to return: ' .$stringToReturn . '<br>';
                        }
                    }
                }
                //echo '-----------------One object iterated------------------<br>';
                $stringToReturn = substr($stringToReturn, 0, -3);
            }
        }
        return $stringToReturn;
    }
    
}
