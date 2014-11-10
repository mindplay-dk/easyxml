<?php

namespace mindplay\easyxml;

use ArrayAccess;
use RuntimeException;
use ReflectionFunction;
use Closure;

/**
 * This class implements a collection of functions to handle every visited
 * XML element and text nodes.
 */
class Visitor implements ArrayAccess
{
    /**
     * @var Closure[] hash where element name => function
     */
    private $_functions = array();

    /**
     * @var string path of current element
     */
    protected $path;

    /**
     * @param string $name element name
     *
     * @return bool
     *
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($name)
    {
        return isset($this->_functions[$name]);
    }

    /**
     * @param string $name element name
     *
     * @return Closure
     *
     * @throws RuntimeException on undefined element name/function
     *
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($name)
    {
        if (isset($this->_functions[$name]) === false) {
            throw new RuntimeException("undefined handler: $name");
        }

        return $this->_functions[$name];
    }

    /**
     * @param string $name element name
     * @param Closure $function
     *
     * @return void
     *
     * @throws RuntimeException
     *
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($name, $function)
    {
        if ($function instanceof Closure === false) {
            throw new RuntimeException("Closure expected");
        }

        $this->_functions[$name] = $function;
    }

    /**
     * @param string $name element name
     *
     * @return void
     *
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($name)
    {
        unset($this->_functions[$name]);
    }

    /**
     * @param string $name element name
     * @param array $values hash where parameter name => value
     *
     * @return Visitor|null generated Visitor for further parsing (or NULL to ignore further elements)
     *
     * @throws RuntimeException if unable to satisfy all the arguments of the dispatched function
     */
    private function dispatchFunction($name, $values = array())
    {
        if (!isset($this->_functions[$name])) {
            return null; // no handler for this element
        }

        $function = $this->_functions[$name];

        $reflection = new ReflectionFunction($function);

        $return = null;

        $params = array();

        foreach ($reflection->getParameters() as $index => $param) {
            $param_name = $param->getName();

            if ($index === 0 && ($class = $param->getClass()) && ($class->name === __CLASS__ || $class->isSubclassOf(__CLASS__))) {
                $params[0] = $class->newInstance();
                $return = $params[0];

                continue;
            }

            if (array_key_exists($param_name, $values)) {
                $params[$index] = $values[$param_name];

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $params[$index] = $param->getDefaultValue();

                continue;
            }

            $func_def = 'function defined in ' . $reflection->getFileName() . ' at line ' . $reflection->getStartLine();

            throw new RuntimeException("unable to satisfy required argument \${$param_name} for {$func_def}");
        }

        call_user_func_array($function, $params);

        return $return;
    }

    /**
     * @param string $name element name
     * @param string[] $attr map of element attributes
     *
     * @return Visitor|null generated Visitor for further parsing (or NULL to ignore further elements)
     */
    public function startElement($name, $attr)
    {
        /**
         * @var array $params
         */

        $this->path = strlen($this->path)
            ? $this->path . '/' . $name
            : $name;

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

        return $this->dispatchFunction($this->path, $params);
    }

    /**
     * @param string $name element name
     */
    public function endElement($name)
    {
        $this->path = substr($this->path, 0, max(0, strlen($this->path) - strlen($name) - 1));

        if (isset($this->_functions[$this->path . '#end'])) {
            $function = $this->_functions[$this->path . '#end'];
            $function();
        }
    }

    /**
     * @param string $data text node content
     */
    public function characterData($data)
    {
        if (isset($this->_functions[$this->path . '#text'])) {
            $function = $this->_functions[$this->path . '#text'];
            $function($data);
        }
    }
}
