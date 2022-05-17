<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * 
 * @author andrej.kabachnik
 *
 */
interface iCanWrapText extends WidgetInterface
{
    public function getNowrap() : bool;
    
    public function setNowrap(bool $value) : iCanWrapText;
}