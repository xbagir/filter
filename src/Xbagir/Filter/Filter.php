<?php namespace Xbagir\Filter;

use Illuminate\Container\Container;

class Filter
{
    protected $container;

    protected $data;
    protected $filters;

    protected $extensions      = array();
    protected $implicitFilters = array();
        
    public function __construct(array $data, array $filters)
    {       
        $this->data    = $data;
        $this->filters = $this->explodeFilters($filters);
    }

    protected function explodeFilters($filters)
    {
        foreach ($filters as $key => &$filter)
        {
            $filter = (is_string($filter)) ? explode('|', $filter) : $filter;
        }

        return $filters;
    }

    public function sometimes($attribute, $filters, $callback)
    {
        $payload = new Fluent($this->data);

        if (call_user_func($callback, $payload))
        {
            foreach ((array) $attribute as $key)
            {
                $this->mergeFilters($key, $filters);
            }
        }
    }

    protected function mergeFilters($attribute, $filters)
    {
        $current = array_get($this->filters, $attribute, array());

        $merge = head($this->explodeFilters(array($filters)));

        $this->filters[$attribute] = array_merge($current, $merge);
    }

    public function passes()
    {
        foreach ($this->filters as $attribute => $filters)
        {
            foreach ($filters as $filter)
            {
                $this->callFilter($attribute, $filter);
            }
        }

        return $this;
    }

    protected function callFilter($attribute, $filter)
    {
        if (trim($filter) == '') return;

        list($filter, $parameters) = $this->parseFilter($filter);

        $value  = $this->getValue($attribute);
        $method = "filter{$filter}";

        if ( $this->isFilterable($filter, $value) )
        {
            $value = $this->$method($attribute, $value, $parameters, $this);
        }

        $this->setValue($attribute, $value);
    }

    protected function getValue($attribute)
    {
        return array_get($this->data, $attribute);
    }

    protected function setValue($attribute, $value)
    {
        array_set($this->data, $attribute, $value);
    }

    protected function isFilterable($filter, $value)
    {
        return $this->isRequired($value) or $this->isImplicit($filter);
    }

    protected function isImplicit($rule)
    {
        return in_array($rule, $this->implicitFilters);
    }

    protected function isRequired($value)
    {
        if (is_null($value))
        {
            return false;
        }
        elseif (is_string($value) and $value === '')
        {
            return false;
        }

        return true;
    }

    protected function filterString($attribute, $value)
    {
        try
        {
            return strval($value);
        }
        catch(\Exception $e)
        {
            return "";
        }
    }
    
    protected function filterInteger($attribute, $value)
    {
        return intval($value);
    }

    protected function filterFloat($attribute, $value)
    {
        return floatval($value);
    }

    protected function filterClean($attribute, $value)
    {
        return strip_tags($value);
    }

    protected function filterTrim($attribute, $value)
    {
        return trim($value);
    }

    protected function filterE($attribute, $value)
    {        
        return e($value);
    }

    protected function parseFilter($filter)
    {
        $parameters = array();

        if (strpos($filter, ':') !== false)
        {
            list($filter, $parameter) = explode(':', $filter, 2);

            $parameters = $this->parseParameters($filter, $parameter);
        }

        return array(studly_case($filter), $parameters);
    }

    protected function parseParameters($rule, $parameter)
    {
        if (strtolower($rule) == 'regex') return array($parameter);

        return str_getcsv($parameter);
    }

    public function getExtensions()
    {
        return $this->extensions;
    }

    public function addExtensions(array $extensions)
    {
        if ($extensions)
        {
            $keys = array_map('snake_case', array_keys($extensions));

            $extensions = array_combine($keys, array_values($extensions));
        }

        $this->extensions = array_merge($this->extensions, $extensions);
    }

    public function addImplicitExtensions(array $extensions)
    {
        $this->addExtensions($extensions);

        foreach ($extensions as $filter => $extension)
        {
            $this->implicitFilters[] = studly_case($filter);
        }
    }

    public function addExtension($filter, $extension)
    {
        $this->extensions[snake_case($filter)] = $extension;
    }

    public function addImplicitExtension($filter, Closure $extension)
    {
        $this->addExtension($filter, $extension);

        $this->implicitRules[] = studly_case($filter);
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function setFilters(array $filters)
    {
        $this->filters = $this->explodeFilters($filters);

        return $this;
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    protected function callExtension($filter, $parameters)
    {
        $callback = $this->extensions[$filter];

        if ($callback instanceof Closure)
        {
            return call_user_func_array($callback, $parameters);
        }
        elseif (is_string($callback))
        {
            return $this->callClassBasedExtension($callback, $parameters);
        }
    }

    protected function callClassBasedExtension($callback, $parameters)
    {
        list($class, $method) = explode('@', $callback);

        return call_user_func_array(array($this->container->make($class), $method), $parameters);
    }

    public function __call($method, $parameters)
    {
        $filter = snake_case(substr($method, 6));

        if (isset($this->extensions[$filter]))
        {
            return $this->callExtension($filter, $parameters);
        }

        throw new \BadMethodCallException("Method [$method] does not exist.");
    }
}