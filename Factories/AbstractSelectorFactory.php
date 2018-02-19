<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Selectors\SelectorInterface;

abstract class AbstractSelectorFactory extends AbstractFactory
{

    public static function create(SelectorInterface $selector)
    {
        $class = $selector->getClassname();
        return new $class();
    }
}
?>