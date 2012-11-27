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
     * @param array $values hash where parameter name => value
     * @return XmlHandler|null
     */
    private function dispatchFunction($name, $values=array())
    {
        if (!isset($this->functions[$name])) {
            return null; // no handler for this element
        }

        $function = $this->functions[$name];

        $reflection = new ReflectionFunction($function);

        $norm_name = strtr($name, '-.:', '___');

        $return = null;

        $params = array();

        foreach ($reflection->getParameters() as $index => $param) {
            $param_name = $param->getName();

            if (($index === 0) && ($param_name === $norm_name)) {
                $params[0] = new XmlHandler();
                $return = $params[0];
            } else {
                if (array_key_exists($param_name, $values)) {
                    $params[$index] = $values[$param_name];
                } else if ($param->isOptional() === false) {
                    $func_def = 'function defined in ' . $reflection->getFileName() . ' at line ' . $reflection->getStartLine();
                    throw new RuntimeException("unable to satisfy required argument $param_name for $func_def");
                }
            }
        }

        call_user_func_array($function, $params);

        return $return;
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
         * @var array $params
         */

        #echo '<pre>[HANDLING '.$name.']</pre>';

        if (count($attr) === 0) {
            $params = array();
        } else {
            $params = array_combine(
                array_map(
                    function ($key) {
                        return strtr($key, '-.:', '___');
                    },
                    array_keys($attr)
                ),
                array_values($attr)
            );
        }

        return $this->dispatchFunction($name, $params);
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
