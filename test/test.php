<?php

use mindplay\easyxml\Parser;
use mindplay\easyxml\Visitor;

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';
$autoloader->addPsr4('mindplay\\easyxml\\', __DIR__ . '/src');

header('Content-type: text/plain');

$SAMPLE = file_get_contents(__DIR__ . '/test.xml');

class Cats
{
    /** @var Cat[] */
    public $cats = array();

    /** @var string */
    public $notes;
}

class Cat
{
    public $name;

    /** @var Kitten[] */
    public $kittens = array();

    /** @var string cat status */
    public $status;
}

class Kitten extends Cat
{}

function testModel(Cats $model)
{
    eq(count($model->cats), 2, 'document contains 2 cats');

    eq(count($model->cats[0]->kittens), 1, 'first cat has 1 kitten');
    eq($model->cats[0]->status, 'happy');
    eq($model->cats[0]->name, 'whiskers');
    eq($model->cats[0]->kittens[0]->name, 'mittens');

    eq(count($model->cats[1]->kittens), 1, 'second cat has 1 kitten');
    eq($model->cats[1]->status, 'happy');
    eq($model->cats[1]->name, 'tinker');
    eq($model->cats[1]->kittens[0]->name, 'binky');

    eq($model->notes, 'Hello World');
}

if (coverage()) {
    $filter = coverage()->filter();

    // whitelist the files to cover:

    $filter->addDirectoryToWhitelist(dirname(__DIR__) . '/src');

    // start code coverage:

    coverage()->start('test');
}

test(
    'Can manage function collection',
    function () {
        $visitor = new Visitor();

        ok(! isset($visitor['foo']), 'function is not defined');

        $function = function () {};

        $visitor['foo'] = $function;

        ok(isset($visitor['foo']), 'function is defined');

        eq($visitor['foo'], $function, 'can get defined function');

        unset($visitor['foo']);

        ok(! isset($visitor['foo']), 'can remove defined function');

        expect(
            'RuntimeException',
            'value is not a function',
            function () use ($visitor) {
                $visitor['foo'] = 'bunk';
            }
        );

        expect(
            'RuntimeException',
            'index does not exist',
            function () use ($visitor) {
                $bunk = $visitor['foo'];
            }
        );
    }
);

test(
    'Injects attributes as parameters',
    function () {
        $parser = new Parser();

        $function_called = false;

        $parser['foo'] = function ($bar = 'baz') use (&$function_called) {
            $function_called = $bar === 'baz';
        };

        $parser->parse('<foo/>');

        ok(true, 'missing argument filled with default value');

        $parser['foo'] = function ($bar) {};

        expect(
            'RuntimeException',
            'Missing required attribute',
            function () use ($parser) {
                $parser->parse('<foo/>');
            }
        );
    }
);

test(
    'Parsing elements and attributes',
    function () use ($SAMPLE) {
        $doc = new Parser();

        $model = new Cats();

        $doc['cats'] = function (Visitor $cats) use ($model) {
            $cats['cat'] = function (Visitor $cat_node, $name) use ($model) {
                $cat = new Cat();
                $cat->name = $name;

                $model->cats[] = $cat;

                $cat_node['kitten'] = function ($name) use ($cat) {
                    $kitten = new Kitten();
                    $kitten->name = $name;

                    $cat->kittens[] = $kitten;
                };

                $cat_node['#end'] = function () use ($cat) {
                    $cat->status = 'happy';
                };
            };

            $cats['notes'] = function (Visitor $notes) use ($model) {
                $notes['#text'] = function ($text) use ($model) {
                    $model->notes = $text;
                };
            };
        };

        $doc->parse($SAMPLE);

        testModel($model);
    }
);

test(
    'Multi-level element matching',
    function () use ($SAMPLE) {
        $doc = new Parser();

        $model = new Cats();

        $doc['cats'] = function (Visitor $cats) use ($model) {
            $cats['cat'] = function (Visitor $cat_node, $name) use ($model) {
                $cat = new Cat();
                $cat->name = $name;

                $model->cats[] = $cat;

                $cat_node['kitten'] = function ($name) use ($cat) {
                    $kitten = new Kitten();
                    $kitten->name = $name;

                    $cat->kittens[] = $kitten;
                };

                $cat_node['#end'] = function () use ($cat) {
                    $cat->status = 'happy';
                };
            };

            $cats['notes#text'] = function ($text) use ($model) {
                $model->notes = $text;
            };
        };

        $doc->parse($SAMPLE);

        testModel($model);
    }
);

test(
    'Flat element matching',
    function () use ($SAMPLE) {
        $doc = new Parser();

        $model = new Cats();

        $doc['cats/cat'] = function (Visitor $cat_node, $name) use ($model) {
            $cat = new Cat();
            $cat->name = $name;

            $model->cats[] = $cat;

            $cat_node['kitten'] = function ($name) use ($cat) {
                $kitten = new Kitten();
                $kitten->name = $name;

                $cat->kittens[] = $kitten;
            };

            $cat_node['#end'] = function () use ($cat) {
                $cat->status = 'happy';
            };
        };

        $doc['cats/notes#text'] = function ($text) use ($model) {
            $model->notes = $text;
        };

        $doc->parse($SAMPLE);

        testModel($model);
    }
);

test(
    'Case-insensitive element and attribute matching (case folding)',
    function () {
        $doc = new Parser();

        $doc->case_folding = true;

        $seen_lowercase_elem = false;
        $seen_uppercase_elem = false;
        $seen_lowercase_attr = false;
        $seen_uppercase_attr = false;

        $doc['root'] = function (Visitor $root) use (&$seen_lowercase_elem, &$seen_uppercase_elem, &$seen_lowercase_attr, &$seen_uppercase_attr) {
            $root['foo'] = function ($bar) use (&$seen_lowercase_elem, &$seen_lowercase_attr) {
                $seen_lowercase_elem = true;

                if ($bar === 'baz') {
                    $seen_lowercase_attr = true;
                }
            };

            $root['bam'] = function ($blam) use (&$seen_uppercase_elem, &$seen_uppercase_attr) {
                $seen_uppercase_elem = true;

                if ($blam === 'BLAZ') {
                    $seen_uppercase_attr = true;
                }
            };
        };

        $doc->parse('<root><foo bar="baz"/><BAM BLAM="BLAZ"/></root>');

        ok($seen_lowercase_elem, 'parses lowercase element');
        ok($seen_lowercase_attr, 'parses lowercase attribute');
        ok($seen_uppercase_elem, 'parses uppercase element');
        ok($seen_uppercase_attr, 'parses uppercase attribute');
    }
);

test(
    'Case-sensitive element and attribute matching',
    function () {
        $doc = new Parser();

        $doc->case_folding = false;

        $seen_lowercase_elem = false;
        $seen_uppercase_elem = false;
        $seen_lowercase_attr = false;
        $seen_uppercase_attr = false;

        $doc['root'] = function (Visitor $root) use (&$seen_lowercase_elem, &$seen_uppercase_elem, &$seen_lowercase_attr, &$seen_uppercase_attr) {
            $root['foo'] = function ($bar) use (&$seen_lowercase_elem, &$seen_lowercase_attr) {
                $seen_lowercase_elem = true;

                if ($bar === 'baz') {
                    $seen_lowercase_attr = true;
                }
            };

            $root['FOO'] = function ($BAR) use (&$seen_uppercase_elem, &$seen_uppercase_attr) {
                $seen_uppercase_elem = true;

                if ($BAR === 'BAZ') {
                    $seen_uppercase_attr = true;
                }
            };
        };

        $doc->parse('<root><foo bar="baz"/><FOO BAR="BAZ"/></root>');

        ok($seen_lowercase_elem, 'parses lowercase element');
        ok($seen_lowercase_attr, 'parses lowercase attribute');
        ok($seen_uppercase_elem, 'parses uppercase element');
        ok($seen_uppercase_attr, 'parses uppercase attribute');
    }
);

foreach (array(10,10000) as $buffer_size) {
    test(
        "Streaming XML content from external file (with a {$buffer_size} byte buffer)",
        function () use ($buffer_size) {
            $doc = new Parser();

            $doc->buffer_size = $buffer_size;

            $model = new Cats();

            $doc['cats'] = function (Visitor $cats) use ($model) {
                $cats['cat'] = function (Visitor $cat_node, $name) use ($model) {
                    $cat = new Cat();
                    $cat->name = $name;

                    $model->cats[] = $cat;

                    $cat_node['kitten'] = function ($name) use ($cat) {
                        $kitten = new Kitten();
                        $kitten->name = $name;

                        $cat->kittens[] = $kitten;
                    };

                    $cat_node['#end'] = function () use ($cat) {
                        $cat->status = 'happy';
                    };
                };

                $cats['notes'] = function (Visitor $notes) use ($model) {
                    $notes['#text'] = function ($text) use ($model) {
                        $model->notes = $text;
                    };
                };
            };

            $doc->parseFile(__DIR__ . '/test.xml');

            testModel($model);
        }
    );
}

test(
    'Expected Exceptions',
    function () {
        $parser = new Parser();

        expect(
            'RuntimeException',
            'file not found',
            function () use ($parser) {
                $parser->parseFile('foo.xml');
            }
        );

        $parser = new Parser();

        expect(
            'mindplay\easyxml\ParserException',
            'Invalid XML',
            function () use ($parser) {
                $parser->parse('><>'); // kinda looks like a fish, dig?
            }
        );

        $parser = new Parser();

        expect(
            'mindplay\easyxml\ParserException',
            'Invalid XML from a file',
            function () use ($parser) {
                $parser->parseFile(__DIR__ . '/junk.xml');
            }
        );
    }
);

if (coverage()) {
    // stop code coverage:

    coverage()->stop();

    // output code coverage report to console:

    $report = new PHP_CodeCoverage_Report_Text(10, 90, false, false);

    echo $report->process(coverage(), false);

    // output code coverage report for integration with CI tools:

    $report = new PHP_CodeCoverage_Report_Clover();

    $report->process(coverage(), dirname(__DIR__) . '/build/logs/clover.xml');
}

exit(status()); // exits with errorlevel (for CI tools etc.)

// https://gist.github.com/mindplay-dk/4260582

/**
 * @param string   $name     test description
 * @param callable $function test implementation
 */
function test($name, $function)
{
    echo "\n=== $name ===\n\n";

    try {
        call_user_func($function);
    } catch (Exception $e) {
        ok(false, "UNEXPECTED EXCEPTION", $e);
    }
}

/**
 * @param bool   $result result of assertion
 * @param string $why    description of assertion
 * @param mixed  $value  optional value (displays on failure)
 */
function ok($result, $why = null, $value = null)
{
    if ($result === true) {
        echo "- PASS: " . ($why === null ? 'OK' : $why) . ($value === null ? '' : ' (' . format($value) . ')') . "\n";
    } else {
        echo "# FAIL: " . ($why === null ? 'ERROR' : $why) . ($value === null ? '' : ' - ' . format($value, true)) . "\n";
        status(false);
    }
}

/**
 * @param mixed  $value    value
 * @param mixed  $expected expected value
 * @param string $why      description of assertion
 */
function eq($value, $expected, $why = null)
{
    $result = $value === $expected;

    $info = $result
        ? format($value)
        : "expected: " . format($expected, true) . ", got: " . format($value, true);

    ok($result, ($why === null ? $info : "$why ($info)"));
}

/**
 * @param string   $exception_type Exception type name
 * @param string   $why            description of assertion
 * @param callable $function       function expected to throw
 */
function expect($exception_type, $why, $function)
{
    try {
        call_user_func($function);
    } catch (Exception $e) {
        if ($e instanceof $exception_type) {
            ok(true, $why, $e);
            return;
        } else {
            $actual_type = get_class($e);
            ok(false, "$why (expected $exception_type but $actual_type was thrown)");
            return;
        }
    }

    ok(false, "$why (expected exception $exception_type was NOT thrown)");
}

/**
 * @param mixed $value
 * @param bool  $verbose
 *
 * @return string
 */
function format($value, $verbose = false)
{
    if ($value instanceof Exception) {
        return get_class($value)
        . ": \"" . $value->getMessage() . "\"";
    }

    if (! $verbose && is_array($value)) {
        return 'array[' . count($value) . ']';
    }

    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }

    if (is_object($value) && !$verbose) {
        return get_class($value);
    }

    return print_r($value, true);
}

/**
 * @return PHP_CodeCoverage|null code coverage service, if available
 */
function coverage()
{
    static $coverage = null;

    if ($coverage === false) {
        return null; // code coverage unavailable
    }

    if ($coverage === null) {
        try {
            $coverage = new PHP_CodeCoverage;
        } catch (PHP_CodeCoverage_Exception $e) {
            echo "# Notice: no code coverage run-time available\n";
            $coverage = false;
            return null;
        }
    }

    return $coverage;
}

/**
 * @param bool|null $status test status
 *
 * @return int number of failures
 */
function status($status = null)
{
    static $failures = 0;

    if ($status === false) {
        $failures += 1;
    }

    return $failures;
}
