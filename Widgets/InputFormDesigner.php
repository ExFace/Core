<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 * Allows to design forms for the InputForm widget
 * 
 * @author Andrej Kabachnik
 *
 */
class InputFormDesigner extends Input implements iFillEntireContainer
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings(): ?iContainOtherWidgets
    {
        return null;
    }
}