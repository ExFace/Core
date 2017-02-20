<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveTopToolbar extends WidgetInterface {
	function get_hide_toolbar_top();
	function set_hide_toolbar_top($boolean);
	function get_hide_toolbars();
	function set_hide_toolbars($boolean);
}