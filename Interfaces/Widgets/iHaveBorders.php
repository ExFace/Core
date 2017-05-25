<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveBorders extends WidgetInterface
{

    public function getShowBorder();

    public function setShowBorder($value);
}