<?php

namespace mindplay\easyxml;

/**
 * This class implements parsing of XML files and content.
 */
class Parser extends Visitor
{
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

        if (xml_parse($parser, $input, true) === false) {
            throw ParserException::create($parser);
        }

        xml_parser_free($parser);
    }

    /**
     * @param string $path absolute path to XML file
     *
     * @return void
     *
     * @throws ParserException if the XML file contains error
     */
    public function parseFile($path)
    {
        /** @var resource $parser */
        $parser = $this->createParser();

        if (!($fp = fopen($path, "r"))) {
            die("could not open XML input: {$path}");
        }

        while ($data = fread($fp, 4096)) {
            if (xml_parse($parser, $data, feof($fp)) === false) {
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
        $parser = xml_parser_create();

        // skip whitespace-only values
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, $this->skip_white);

        // disable case-folding - read XML element/attribute names as-is:
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);

        xml_set_element_handler($parser, array($this, 'onStartElement'), array($this, 'onEndElement'));

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

        // Notify current Visitor and push the next Visitor onto the stack:

        $next_visitor = $this->visitor->startElement($name, $attr);

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

        // Get previous Visitor from stack and notify:

        array_pop($this->visitors);

        $this->visitor = null;

        for ($n=count($this->visitors) - 1; $n >= 0 && !$this->visitor; $n--) {
            $this->visitor = $this->visitors[$n];
        }

        $this->visitor->endElement($name);
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
