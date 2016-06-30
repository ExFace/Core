<?php
namespace exface\Core\Widgets;
class InputNumber extends Input {
	private $precision = 3;
	private $min_value = false;
	private $max_value = false;
	private $decimal_separator = ',';
	private $thousand_separator = ' ';
	private $prefix = '';
	private $suffix = '';
	
	public function get_precision() {
		return $this->precision;
	}
	
	public function set_precision($value) {
		$this->precision = $value;
	}
	
	public function get_min_value() {
		return $this->min_value;
	}
	
	public function set_min_value($value) {
		$this->min_value = $value;
	}
	
	public function get_max_value() {
		return $this->max_value;
	}
	
	public function set_max_value($value) {
		$this->max_value = $value;
	}
	
	public function get_decimal_separator() {
		return $this->decimal_separator;
	}
	
	public function set_decimal_separator($value) {
		$this->decimal_separator = $value;
	}
	
	public function get_thousand_separator() {
		return $this->thousand_separator;
	}
	
	public function set_thousand_separator($value) {
		$this->thousand_separator = $value;
	}
	
	public function get_prefix() {
		return $this->prefix;
	}
	
	public function set_prefix($value) {
		$this->prefix = $value;
	}
	
	public function get_suffix() {
		return $this->suffix;
	}
	
	public function set_suffix($value) {
		$this->suffix = $value;
	}
	              
}
?>