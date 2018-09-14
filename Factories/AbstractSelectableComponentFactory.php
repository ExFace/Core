<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Selectors\PrototypeSelectorInterface;
use exface\Core\Exceptions\LogicException;

/**
 * This generic factory for components supporting selectors can route most
 * selector types automatically to their apps.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractSelectableComponentFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param AliasSelectorInterface|PrototypeSelectorInterface $selector
     * @return mixed
     */
    public static function createFromSelector(SelectorInterface $selector)
    {
        if ($selector instanceof AliasSelectorInterface) {
            $appSelector = $selector->getAppSelector();
        } elseif ($selector instanceof PrototypeSelectorInterface) {
            $appSelector = $selector->getPrototypeAppSelector();
        } else {
            throw new LogicException('Cannot determine the app from ' . get_class($selector) . ' "' . $selector->toString() . '" automatically: please use custom factory logic or provide a selector based on aliases or prototypes.');
        }
        return $selector->getWorkbench()->getApp($appSelector)->get($selector);
    }
}
?>