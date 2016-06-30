<?php
namespace exface\Core\Interfaces\Widgets;
interface iTakeInput extends iHaveValue {
	public function is_required();
	public function set_required($value);
	public function is_disabled();
	public function set_disabled($value);
}