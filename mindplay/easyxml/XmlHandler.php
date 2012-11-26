<?php

namespace mindplay\easyxml;

use ArrayAccess;
use RuntimeException;
use ReflectionFunction;
use Closure;

/**
 * XML Event Handler
 */
class XmlHandler implements ArrayAccess
{
    /**
     * @var Closure[] hash where element name => function
     */
    private $functions = array();

    /**
     * @param string $name
     * @return bool
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($name)
    {
        return isset($this->functions[$name]);
    }

    /**
     * @param string $name
     * @return Closure
     * @throws RuntimeException
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($name)
    {
        if (isset($this->functions[$name]) === false) {
            throw new RuntimeException("undefined handler: $name");
        }

        return $this->functions[$name];
    }

    /**
     * @param string $name
     * @param Closure $function
     * @throws RuntimeException
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($name, $function)
    {
        if ($function instanceof Closure === false) {
            throw new RuntimeException("Closure expected");
        }

        $this->functions[$name] = $function;
    }

    /**
     * @param string $name
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($name)
    {
        unset($this->functions[$name]);
    }

    /**
     * @param string $name
     * @param string $attr
     * @return XmlHandler|null
     * @throws RuntimeException if function requires a missing attribute
     */
    public function startElement($name, $attr)
    {
        /**
         * @var Closure $function
         */

        #echo '<pre>[HANDLING '.$name.']</pre>';

        if (!isset($this->functions[$name])) {
            return null; // no handler for this element
        }

        $function = $this->functions[$name];

        $norm_name = strtr($name, '-.:', '___');

        $reflection = new ReflectionFunction($function);

        $params = array();

        $return = null;

        if (count($attr) !== 0) {
            $norm_attr = array_combine(
                array_map(
                    function ($key) {
                        return strtr($key, '-.:', '___');
                    },
                    array_keys($attr)
                ),
                array_values($attr)
            );
        } else {
            $norm_attr = array();
        }

        foreach ($reflection->getParameters() as $index => $param) {
            $param_name = $param->getName();

            if (($index === 0) && ($param_name === $norm_name)) {
                $params[0] = new XmlHandler();
                $return = $params[0];
            } else {
                if (array_key_exists($param_name, $norm_attr)) {
                    $params[$index] = $norm_attr[$param_name];
                } else if ($param->isOptional() === false) {
                    throw new RuntimeException("required attribute $param_name not found");
                }
            }
        }

        call_user_func_array($function, $params);

        return $return;
    }

    public function endElement($name)
    {
        #echo '<pre>[HANDLED '.$name.']</pre>';

        if (isset($this->functions['#end'])) {
            $function = $this->functions['#end'];
            $function();
        }
    }

    public function characterData($data)
    {
        if (isset($this->functions['#text'])) {
            $function = $this->functions['#text'];
            $function($data);
        }
    }
}
