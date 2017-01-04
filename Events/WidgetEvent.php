<?php namespace exface\Core\Events;

use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Widget event names consist of the qualified alias of the app followed by "Widget" and the respective event type:
 * e.g. ..., etc.
 * @author Andrej Kabachnik
 */
class WidgetEvent extends ExFaceEvent {
	private $widget = null;

	public function get_widget() {
		return $this->widget;
	}

	public function set_widget(WidgetInterface &$widget) {
		$this->widget = $widget;
		return $this;
	}

	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Events\ExFaceEvent::get_namespace()
	 */
	public function get_namespace(){
		return $this->get_widget()->get_meta_object()->get_alias_with_namespace() . NameResolver::NAMESPACE_SEPARATOR . 'Widget';
	}
}