<?php

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';
$autoloader->addPsr4('mindplay\\easyxml\\', __DIR__ . '/src');

use mindplay\easyxml\Parser;
use mindplay\easyxml\Visitor;

header('Content-type: text/plain');

// Define a simple model for the file we're going to read:

class Catalog
{
    /**
     * @var CD[]
     */
    public $cds = array();
}

class CD
{
    public $title;
    public $artist;
    public $country;
    public $company;
    public $price;
    public $year;
}

// Create and configure the XML reader:

$model = new Catalog();

$doc = new Parser();

$doc->case_folding = true;

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

// Run it:

$path = dirname(__FILE__) . '/cd_catalog.xml';

$doc->parseFile($path);

// Dump the result:

var_dump($model);
