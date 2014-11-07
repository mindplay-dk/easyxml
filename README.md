mindplay/easyxml
----------------

Functional XML-reader for PHP 5.3+.

THIS LIBRARY IS A WORK IN PROGRESS.

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

See "test.php" and "example/cd_catalog.php" for a working example of how to use this.


TODO
----

  * Increase test coverage
  * Debug memory leaks / rewrite support for path matchingX
  * Add support for wildcard matching
