<?php

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

require __DIR__ . '/model.php';

use mindplay\easyxml\Parser;
use mindplay\easyxml\Visitor;

header('Content-type: text/plain');

// Create and configure the XML reader:

$model = new Catalog();

// Run it:

$model->load(dirname(__FILE__) . '/cd_catalog.xml');

// Dump the result:

var_dump($model);
