<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveBottomToolbar extends WidgetInterface
{

    function getHideToolbarBottom();

    function setHideToolbarBottom($boolean);

    function getHideToolbars();

    function setHideToolbars($boolean);
}