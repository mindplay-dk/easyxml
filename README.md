easyxml
=======

Functional XML-reader for PHP 5.3+.

THIS LIBRARY IS A WORK IN PROGRESS.

A somewhat different approach to reading/parsing XML files with PHP, using a hierarchy
of anonymous (closures) reflecting the hierarchy of the XML document itself.

This is useful when reading structured XML documents - e.g. XML documents with a
predictable structure. It's probably less than enjoyable when reading unstructured
documents such as XHTML documents.

It's fairly fast and memory-efficient, e.g. avoiding the overhead of loading the entire
document into memory and performing repetitive XPATH queries against it.

See "example/cd_catalog.php" for a working example of how to use this.

TODO
----

  * Add missing documentation
  * Add support for wildcard matching (e.g. '*' to handle all incoming elements)
  * Add support for (x) path matching (elements with a specific path, e.g. '/foo/bar/baz')
  * Add unit test
