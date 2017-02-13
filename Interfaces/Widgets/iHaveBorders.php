<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveBorders extends WidgetInterface {
	public function get_show_border();
	public function set_show_border($value);	  
}