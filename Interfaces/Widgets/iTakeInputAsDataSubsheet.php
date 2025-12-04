<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Common interface for input widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iTakeInputAsDataSubsheet extends iTakeInput
{
    public function isSubsheetForObject(MetaObjectInterface $objectOfInputData) : bool;
}