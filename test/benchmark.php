<?php

use mindplay\benchpress\Benchmark;

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';
$autoloader->addPsr4('mindplay\\easyxml\\', dirname(__DIR__) . '/src');

require __DIR__ . '/example/model.php';

header('Content-type: text/plain');

echo "Benchmarking, please wait...\n\n";

$bench = new Benchmark();

$path = __DIR__ . '/example/cd_catalog.xml';

$test = function () use ($path) {
    $model = new Catalog();

    $model->load($path);
};

$time = $bench->mark($test, $elapsed, $marks, $iterations);

$size = filesize($path);

$total = $size * $iterations;

$rate = ($total / 1024) / ($elapsed / 1000);

echo "Parsed a {$size} bytes file {$iterations} times in {$elapsed} msec (average {$time} msec per operation)\n";
echo "Total content parsed: {$total} bytes\n";
echo "Average throughput: {$rate} KB/sec\n";
