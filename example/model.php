<?php

use mindplay\easyxml\Parser;
use mindplay\easyxml\Visitor;

// Define a simple model for the file we're going to read:

class Catalog
{
    /**
     * @var CD[]
     */
    public $cds = array();

    /**
     * @param string $path absolute path to XML file
     *
     * @return Parser
     */
    public function load($path)
    {
        $doc = new Parser();

        $doc->case_folding = true;

        $model = $this;

        $doc['catalog/cd'] = function (Visitor $cd) use ($model) {
            $item = new CD();

            $model->cds[] = $item;

            $cd['title#text'] = function ($text) use ($item) {
                $item->title = trim($text);
            };

            $cd['artist#text'] = function ($text) use ($item) {
                $item->artist = trim($text);
            };

            $cd['country#text'] = function ($text) use ($item) {
                $item->country = trim($text);
            };

            $cd['company#text'] = function ($text) use ($item) {
                $item->company = trim($text);
            };

            $cd['price#text'] = function ($text) use ($item) {
                $item->price = floatval($text);
            };

            $cd['year#text'] = function ($text) use ($item) {
                $item->year = intval($text);
            };
        };

        $doc->parseFile($path);
    }
}

class CD
{
    /** @var string */
    public $title;

    /** @var string */
    public $artist;

    /** @var string */
    public $country;

    /** @var string */
    public $company;

    /** @var float */
    public $price;

    /** @var int */
    public $year;
}
