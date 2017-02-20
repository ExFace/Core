<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Interfaces\WidgetInterface;

interface iAmMaximizable extends WidgetInterface {
	function set_maximizable($value);
	function get_maximizable();
	function set_maximized();
	function get_maximized();
}