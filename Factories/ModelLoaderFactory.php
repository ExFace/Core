<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\Interfaces\Selectors\ModelLoaderSelectorInterface;

abstract class ModelLoaderFactory extends AbstractSelectableComponentFactory
{

    /**
     * Creates a ModelLoader
     *
     * @param ModelLoaderSelectorInterface $name_resolver            
     * @return ModelLoaderInterface
     */
    public static function create(ModelLoaderSelectorInterface $selector) : ModelLoaderInterface
    {
        return static::createFromSelector($selector);
    }
}
?>