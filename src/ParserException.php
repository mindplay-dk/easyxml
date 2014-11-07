<?php

namespace mindplay\easyxml;

use RuntimeException;

class ParserException extends RuntimeException
{
    /**
     * @param resource $parser
     * @param string|null $path
     *
     * @return self
     */
    public static function create($parser, $path = null)
    {
        return new ParserException(
            sprintf(
                "XML error: %s at line %d in %s",
                xml_error_string(xml_get_error_code($parser)),
                xml_get_current_line_number($parser),
                $path === null ? 'input' : $path
            )
        );
    }
}
