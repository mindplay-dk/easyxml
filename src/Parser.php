<?php

namespace mindplay\easyxml;
use RuntimeException;

/**
 * This class implements parsing of XML files and content.
 */
class Parser extends Visitor
{
    const ENCODING_UTF8 = 'UTF-8';
    const ENCODING_ISO = 'ISO-8859-1';
    const ENCODING_ASCII = 'US-ASCII';

    /**
     * @var string input character set encoding (defaults to UTF-8)
     *
     * @see Parser::ENCODING_UTF8
     * @see Parser::ENCODING_ISO
     * @see Parser::ENCODING_ASCII
     *
     * @see createParser()
     */
    public $encoding = self::ENCODING_UTF8;

    /**
     * @var bool if true, enable case-folding (read all element/attribute-names in lower-case)
     */
    public $case_folding = false;

    /**
     * @var bool if true, ignore whitespace between elements
     */
    public $skip_white = true;

    /**
     * @var bool if true, trim leading/trailing whitespace in text nodes
     */
    public $trim_text = true;

    /**
     * @var int buffer size in bytes (when reading XML files)
     *
     * @see parseFile()
     */
    public $buffer_size = 4096;

    /**
     * @var Visitor[] $visitors node visitor stack
     */
    protected $visitors;

    /**
     * @var Visitor $visitor most recent Visitor
     */
    protected $visitor;

    /**
     * @var string character data buffer
     */
    private $_buffer;

    /**
     * @var string[][] map where namespace xmlns-prefix => stack of namespace URIs
     */
    private $ns_uri = array();

    /**
     * @var string[] map where namespace URI => user-defined namespace prefix
     */
    private $ns_prefix = array();

    /**
     * @var string[][] stack where each entry is a list of namespace prefixes started at the corresponding depth
     */
    private $ns_stack = array();

    /**
     * @param string $input XML input
     *
     * @return void
     *
     * @throws ParserException if the XML input contains error
     */
    public function parse($input)
    {
        /** @var resource $parser */
        $parser = $this->createParser();

        if (xml_parse($parser, $input, true) !== 1) {
            throw ParserException::create($parser);
        }

        xml_parser_free($parser);
    }

    /**
     * Set the alias used for a namespace URI in Visitors.
     *
     * @param string $uri namespace URI
     * @param string $alias
     */
    public function setPrefix($uri, $alias)
    {
        $this->ns_prefix[$uri] = $alias;
    }

    /**
     * @param string $path absolute path to XML file
     *
     * @return void
     *
     * @throws RuntimeException if the XML file was not found
     * @throws ParserException if the XML file contains error
     */
    public function parseFile($path)
    {
        /** @var resource $parser */
        $parser = $this->createParser();

        $file = @fopen($path, "r");

        if ($file === false) {
            throw new RuntimeException("could not open XML file: {$path}");
        }

        while ($data = fread($file, $this->buffer_size)) {
            if (xml_parse($parser, $data, feof($file)) !== 1) {
                throw ParserException::create($parser, $path);
            }
        }

        xml_parser_free($parser);
    }

    /**
     * Create and configure the XML parser.
     *
     * @return resource
     */
    protected function createParser()
    {
        // reset the stack:
        $this->visitor = $this;
        $this->visitors = array($this);

        // reset the character data buffer:
        $this->_buffer = '';

        // create and configure the parser:
        $parser = xml_parser_create($this->encoding);

        // skip whitespace-only values:
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, $this->skip_white);

        // disable case-folding - read XML element/attribute names as-is:
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);

        // handle element start/end:
        xml_set_element_handler($parser, array($this, 'onStartElement'), array($this, 'onEndElement'));

        // handle character data:
        xml_set_character_data_handler($parser, array($this, 'onCharacterData'));

        return $parser;
    }

    /**
     * @param resource $parser XML parser
     * @param string   $name   element name
     * @param string[] $attr   map of attributes
     *
     * @return void
     *
     * @see parse()
     * @see xml_set_element_handler()
     */
    protected function onStartElement($parser, $name, $attr)
    {
        // Flush the character data buffer:

        $this->_flushBuffer();

        // Apply case folding:

        if ($this->case_folding === true) {
            $name = strtolower($name);

            if (count($attr)) {
                $attr = array_combine(
                    array_map('strtolower', array_keys($attr)),
                    array_values($attr)
                );
            }
        }

        // Handle XML namespace declarations:

        $this->ns_stack[] = array();

        foreach ($attr as $attr_name => $value) {
            if (strncmp($attr_name, "xmlns:", 6) === 0) {
                $prefix = substr($attr_name, 6);

                $this->ns_uri[$prefix][] = $value; // URI

                $this->ns_stack[count($this->ns_stack) - 1][] = $prefix;
            }
        }

        // Notify current Visitor and push the next Visitor onto the stack:

        $next_visitor = $this->visitor->startElement($this->applyUserPrefix($name), $attr);

        $this->visitor = $next_visitor ?: $this->visitor;

        $this->visitors[] = $next_visitor;
    }

    /**
     * @param resource $parser XML parser
     * @param string   $name   element name
     *
     * @return void
     *
     * @see parse()
     * @see xml_set_element_handler()
     */
    protected function onEndElement($parser, $name)
    {
        // Flush the character data buffer:

        $this->_flushBuffer();

        // Apply case folding:

        if ($this->case_folding === true) {
            $name = strtolower($name);
        }

        // Handle XML namespaces falling out of scope:

        $prefixes = array_pop($this->ns_stack);

        foreach ($prefixes as $prefix) {
            array_pop($this->ns_uri[$prefix]);
        }

        // Get previous Visitor from stack and notify:

        array_pop($this->visitors);

        $this->visitor = null;

        for ($n=count($this->visitors) - 1; $n >= 0 && !$this->visitor; $n--) {
            $this->visitor = $this->visitors[$n];
        }

        $this->visitor->endElement($this->applyUserPrefix($name));
    }

    /**
     * @param resource $parser XML parser
     * @param string   $data   partial text node content
     *
     * @return void
     *
     * @see parse()
     * @see xml_set_character_data_handler()
     */
    protected function onCharacterData($parser, $data)
    {
        // Buffer the character data:

        $this->_buffer .= $data;
    }

    /**
     * Map namespace prefix defined in XML (by xmlns-attribute) to a user-defined prefix.
     *
     * For example, `a:foo`, where `a` resolves to `http://foo/`, and a user-defined alias has been
     * defined for that URI as `b`, the resolved name is `b_foo` - e.g. suitable for parameter injection.
     *
     * @param string $name
     *
     * @return string
     */
    private function applyUserPrefix($name)
    {
        $pos = strpos($name, ":");

        if ($pos === false) {
            return $name; // name isn't namespaced
        }

        $prefix = substr($name, 0, $pos);

        if (empty($this->ns_uri[$prefix])) {
            return $name; // TODO QA: throw for undefined namespace in file?
        }

        $uri = $this->ns_uri[$prefix][count($this->ns_uri[$prefix]) - 1];

        if (!isset($this->ns_prefix[$uri])) {
            return $name; // TODO QA: throw for namespace with no user-defined alias?
        }

        $user_prefix = $this->ns_prefix[$uri];

        return "{$user_prefix}_" . substr($name, $pos + 1);
    }

    /**
     * Flush any buffered text node content to the current visitor.
     *
     * @return void
     */
    private function _flushBuffer()
    {
        if ($this->trim_text) {
            $this->_buffer = trim($this->_buffer);
        }

        if ($this->_buffer === '') {
            return;
        }

        // Notify top-most handler on current stack:

        $this->visitor->characterData($this->_buffer);

        // Clear the character data buffer:

        $this->_buffer = '';
    }
}
