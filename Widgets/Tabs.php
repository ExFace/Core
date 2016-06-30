<?php
namespace exface\Widgets;
use exface\Core\Exceptions\UiWidgetConfigException;
use exface\Core\Factories\WidgetFactory;
/**
 * Tabs is a special container widget, that holds one or more Tab widgets allowing the
 * typical tabbed navigation between them. Tabs will typically show the contents of
 * the active tab and a navbar to enable the user to change tabs. The position of that
 * navbar can be determined by the tab_position attribute. Most typical position is "top".
 * @author aka
 *
 */
class Tabs extends Container {
	private $tab_position = 'top';
	private $active_tab = 1;
	
	public function get_tabs() {
		return $this->get_widgets();
	}
	
	public function set_tabs(array $widget_or_uxon_array) {
		return $this->set_widgets($widget_or_uxon_array);
	}
	
	public function get_tab_position() {
		return $this->tab_position;
	}
	
	public function set_tab_position($value) {
		if ($value != 'top'
		&& $value != 'bottom'
		&& $value != 'left'
		&& $value != 'right'){
			throw new UiWidgetConfigException('Tab position accepts only the following values: top, left, right, bottom. "' . $value . '" given!');
		} else {
			$this->tab_position = $value;
		}
	}  
	
	public function get_active_tab() {
		return $this->active_tab;
	}
	
	public function set_active_tab($value) {
		$this->active_tab = $value;
		return $this;
	}  
	
	/**
	 * Adding widgets to Tabs will automatically produce Tab widgets for each added widget, unless it already is a tab. This
	 * way, a short an understandable notation of tabs is possible: simply add any type of widget to the tabs array and 
	 * see them be displayed in tabs.
	 * @see \exface\Widgets\Container::set_widgets()
	 */
	public function set_widgets(array $widget_or_uxon_array){
		$widgets = array();
		foreach ($widget_or_uxon_array as $w){
			if ($w instanceof \stdClass || $w instanceof AbstractWidget){
				// If we have a UXON or instantiated widget object, use the widget directly
				$page = $this->get_page();
				$widget = WidgetFactory::create_from_anything($page, $w, $this);
			} else {
				// If it is something else, just add it to the result and let the parent object deal with it
				$widgets[] = $w;
			}
				
			// If the widget is not a SplitPanel itslef, wrap it in a SplitPanel. Otherwise add it directly to the result.
			if (!($widget instanceof Tab)){				
				$widgets[] = $this->create_tab($widget);
			} else {
				$widgets[] = $widget;
			}
		}
	
		// Now the resulting array consists of widgets and unknown items. Send it to the parent class. Widgets will get
		// added directly and the unknown types may get some special treatment or just lead to errors. We don't handle
		// them here in order to ensure centralised processing in the container widget.
		return parent::set_widgets($widgets);
	}
	
	private function create_tab(AbstractWidget $contents = null){
		// Create an empty tab
		$widget = $this->get_page()->create_widget('Tab', $this);
		
		// If any contained widget is specified, add it to the tab an inherit some of it's attributes
		if ($contents){
			$widget->add_widget($contents);
			$widget->set_meta_object_id($contents->get_meta_object_id());
			$widget->set_caption($contents->get_caption());
		}
		
		return $widget;
	}
	
	/**
	 * Adds the given widget as a new tab. The position (sequential number) of the tab can
	 * be specified optionally. If the given widget is not a tab itself, it will be wrapped
	 * in a Tab widget.
	 * @see add_widget()
	 * @param AbstractWidget $widget
	 * @param int $position
	 */
	public function add_tab(AbstractWidget $widget, $position = null){
		if ($widget instanceof Tab){
			$tab = $widget;
		} else {
			$tab = $this->create_tab($widget);
		}
		return $this->add_widget($tab, $position);
	}
}
?>