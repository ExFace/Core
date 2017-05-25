<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveTopToolbar extends WidgetInterface
{

    function getHideToolbarTop();

    function setHideToolbarTop($boolean);

    function getHideToolbars();

    function setHideToolbars($boolean);
}