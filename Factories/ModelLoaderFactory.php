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
        $class = $name_resolver->getClassNameWithNamespace();
        return new $class($name_resolver);
    }
}
?>