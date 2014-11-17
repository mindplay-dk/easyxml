<?php

use mindplay\benchpress\Benchmark;

require dirname(__DIR__) . '/vendor/autoload.php';

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
