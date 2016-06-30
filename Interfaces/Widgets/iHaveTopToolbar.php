<?php
namespace exface\Core\Interfaces\Widgets;
interface iHaveTopToolbar {
	function get_hide_toolbar_top();
	function set_hide_toolbar_top($boolean);
	function get_hide_toolbars();
	function set_hide_toolbars($boolean);
}