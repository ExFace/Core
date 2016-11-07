<?php namespace exface\Core\Interfaces\Widgets;

interface iTakeInput extends iCanBeRequired {
	public function is_disabled();
	public function set_disabled($value);
}