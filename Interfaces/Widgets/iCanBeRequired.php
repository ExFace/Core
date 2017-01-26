<?php namespace exface\Core\Interfaces\Widgets;

interface iCanBeRequired extends iHaveValue {
	public function is_required();
	public function set_required($value);
}