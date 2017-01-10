<?php
namespace exface\Core\Interfaces\Widgets;
interface iShowText extends iCanBeAligned {
	
	public function get_size();
	
	public function set_size($value);
	
	public function get_style();
	
	public function set_style($value);
	   
}