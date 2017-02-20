<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveBottomToolbar extends WidgetInterface {
	function get_hide_toolbar_bottom();
	function set_hide_toolbar_bottom($boolean);
	function get_hide_toolbars();
	function set_hide_toolbars($boolean);
}