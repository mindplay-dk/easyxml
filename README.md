mindplay/easyxml
----------------

Functional XML-reader for PHP 5.3+.

A somewhat different approach to reading/parsing XML files with PHP, using a hierarchy
of anonymous functions (closures) reflecting the hierarchy of the XML document itself.

This is useful when reading structured XML documents - e.g. XML documents with a
predictable structure. It's probably less than enjoyable when reading unstructured
documents, such as XHTML documents.

Parsing happens on-the-fly, e.g. avoiding the overhead of loading an entire document
into memory and performing repetitive queries against it. This approach is memory
efficient, enabling you to parse very large documents in a streaming fashion - it is
not (by design) extremely fast, but XML parsing is never truly fast, so you should
definitely always cache the parsed results.


Usage
-----

Let's say you wish to read the following XML file:

```XML
<?xml version="1.0" encoding="UTF-8"?>
<cats>
    <cat name="whiskers">
        <kitten name="mittens"/>
    </cat>
    <cat name="tinker">
        <kitten name="binky"/>
    </cat>
</cats>
```

Your reader might look something like this:

```PHP
$doc = new Parser();

$doc['cats/cat'] = function (Visitor $cat, $name) {
    echo "a cat named: {$name}\n";

    $cat['kitten'] = function ($name) {
        echo "a kitten named: {$name}\n";
    };
};

$doc->parseFile('my_cats.xml');
```

The output would be this:

```
a cat named: whiskers
a kitten named: mittens
a cat named: tinker
a kitten named: binky
```

If it's not obvious, the path `cats/cat` designates a `<cat>` node inside a `<cats>` node.

You can also match text-nodes, e.g. a path like `foo/bar#text` will match `YO` in `<foo><bar>YO</bar></foo>`.

And finally, you can use `#end` to match closing tags, if needed.

Incidentally, I don't actually have cats - but if I did, you can bet those would be their names.

See "test.php" and "example/cd_catalog.php" for more examples of how to use this.
