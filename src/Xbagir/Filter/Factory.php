<?php namespace Xbagir\Filter;

use Illuminate\Container\Container;

class Factory
{
    protected $container;

    protected $extensions         = array();
    protected $implicitExtensions = array();

    protected $resolver;
    
    public function __construct(Container $container = null)
    {
        $this->container = $container;
    }
    
    public function one($data, $filters)
    {
        $filter = $this->resolve(array($data), array($filters));

        if ( ! is_null($this->container))
        {
            $filter->setContainer($this->container);
        }

        $filter->addExtensions($this->extensions);

        return $filter->passes()->getData()[0];
    }
    
    public function collection(array $data, array $filters)
    {
        $filter = $this->resolve($data, $filters);

        if ( ! is_null($this->container))
        {
            $filter->setContainer($this->container);
        }
        
        $filter->addExtensions($this->extensions);

        return $filter->passes()->getData();
    }

    protected function resolve($data, $filters)
    {
        if (is_null($this->resolver))
        {
            return new Filter($data, $filters);
        }
        else
        {
            return call_user_func($this->resolver, $data, $filters);
        }
    }

    public function extend($filter, $extension)
    {
        $this->extensions[$filter] = $extension;
    }

    public function extendImplicit($filter, Closure $extension)
    {
        $this->implicitExtensions[$filter] = $extension;
    }

    public function resolver(Closure $resolver)
    {
        $this->resolver = $resolver;
    }
}