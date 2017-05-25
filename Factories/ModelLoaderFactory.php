<?php

namespace exface\Core\Factories;

use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;

abstract class ModelLoaderFactory extends AbstractNameResolverFactory
{

    /**
     * Creates a ModelLoader
     * 
     * @param NameResolverInterface $name_resolver            
     * @return ModelLoaderInterface
     */
    public static function create(NameResolverInterface $name_resolver)
    {
        return parent::create($name_resolver);
    }
}
?>