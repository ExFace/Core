<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Common interface filters
 * 
 * @author Andrej Kabachnik
 *
 */
interface iFilterData extends WidgetInterface
{
    public function getApplyOnChange() : bool;
}