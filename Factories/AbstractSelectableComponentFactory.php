<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Selectors\SelectorInterface;

abstract class AbstractSelectableComponentFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param SelectorInterface $selector
     * @return mixed
     */
    public static function createFromSelector(SelectorInterface $selector)
    {
        return $selector->getWorkbench()->getApp($selector->getAppSelector())->get($selector);
    }
}
?>