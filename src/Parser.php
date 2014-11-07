<?php

namespace mindplay\easyxml;

/**
 * This class implements parsing of XML files and content.
 */
class Parser extends Visitor
{
    /**
     * @var bool if true, diagnostic output will be generated
     */
    public $debug = false;

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

        // Diagnostic output:

        if ($this->debug === true) {
            echo "<pre>&lt;$name";

            foreach ($attr as $attrname => $value) {
                echo " $attrname=\"$value\"";
            }

            echo "&gt;</pre>";
        }

        // Notify current handler and push the next handler onto the stack:

        $handler = $this->visitors[count($this->visitors) - 1];

        $this->visitors[] = ($handler === null)
            ? null
            : $handler->startElement($name, $attr);
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

        // Diagnostic output:

        if ($this->debug === true) {
            echo "<pre>&lt;/$name&gt;</pre>";
        }

        // Pop handler from stack and notify:

        $handler = array_pop($this->visitors);

        if ($handler !== null) {
            $handler->endElement($name);
        }
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
        // Diagnostic output:

        if ($this->debug === true) {
            echo "<pre>" . $data . "</pre>";
        }

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
        if ($this->_buffer === '') {
            return;
        }

        // Notify top-most handler on current stack:

        $handler = $this->visitors[count($this->visitors) - 1];

        if ($handler !== null) {
            $handler->characterData($this->trim_text ? trim($this->_buffer) : $this->_buffer);
        }

        // Clear the character data buffer:

        $this->_buffer = '';
    }
}
