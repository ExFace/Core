<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iAmMaximizable extends WidgetInterface
{

    function setMaximizable($value);

    function getMaximizable();

    function setMaximized();

    function getMaximized();
}