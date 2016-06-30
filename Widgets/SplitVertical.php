<?php
namespace exface\Widgets;
use exface\Core\Factories\WidgetFactory;

/**
 * A Split consists of multiple panels aligned vertically or horizontally. Using splits groups of
 * widgets can be positioned next to each other instead of one-after-another. The borders between
 * panels within a split can be dragged, thus resizing parts of the split.
 * 
 * Splits use special panels: SplitPanels. However, you can pass any widget in the panels or widgets array.
 * In this case, a separate Panel will be automatically created for each widget.
 * 
 * @author PATRIOT
 *
 */
class SplitVertical extends Container {
	/**
	 * Creates a new SplitPanel for this Split and returns it. The panel is not automatically added to the panels collection!
	 * @return SplitPanel
	 */
	private function create_split_panel(){
		$widget = $this->get_page()->create_widget('SplitPanel', $this);
		return $widget;
	}
	
	/**
	 * Returns the panels of the Split. Technically it is an alias for Split::get_widgets() for better readability.
	 * @see get_widgets()
	 */
	public function get_panels(){
		return $this->get_widgets();
	}
	
	public function set_panels(array $widget_or_uxon_array){
		return $this->set_widgets($widget_or_uxon_array);
	}
	
	/**
	 * Adding widgets to a Split will automatically produce SplitPanels for each widget, unless it already is one. This
	 * way, a short an understandable notation of splits is possible: simply add any type of widget to the panels or widgets
	 * array and see them be displayed in the split.
	 * @see \exface\Widgets\Container::set_widgets()
	 */
	public function set_widgets(array $widget_or_uxon_array){
		$widgets = array();
		foreach ($widget_or_uxon_array as $w){
			if ($w instanceof \stdClass){
				// If we have a UXON object, instantiate the widget first
				if (!$w->widget_type){
					$w->widget_type = 'SplitPanel';
				}
				$page = $this->get_page();
				$widget = WidgetFactory::create_from_anything($page, $w, $this);
			} elseif ($w instanceof AbstractWidget) {
				// If it is already a widget, take it for further checks
				$widget = $w;
			} else {
				// If it is something else, just add it to the result and let the parent object deal with it
				$widgets[] = $this->add_widget($w);
			}
			
			// If the widget is not a SplitPanel itslef, wrap it in a SplitPanel. Otherwise add it directly to the result.
			if (!($widget instanceof SplitPanel)){
				$panel = $this->create_split_panel();
				$panel->set_height($widget->get_height());
				$panel->set_width($widget->get_width());
				$panel->add_widget($widget);
				$widgets[] = $panel;
			} else {
				$widgets[] = $widget;
			}
		}
		
		// Now the resulting array consists of widgets and unknown items. Send it to the parent class. Widgets will get
		// added directly and the unknown types may get some special treatment or just lead to errors. We don't handle
		// them here in order to ensure centralised processing in the container widget.
		return parent::set_widgets($widgets);
	}
}
?>