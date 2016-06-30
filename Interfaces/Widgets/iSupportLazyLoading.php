<?php
namespace exface\Core\Interfaces\Widgets;
interface iSupportLazyLoading {
	public function get_lazy_loading();
	public function set_lazy_loading($value);
	public function get_lazy_loading_action();
	public function set_lazy_loading_action($value);
}