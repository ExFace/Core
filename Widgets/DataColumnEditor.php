<?php
namespace exface\Widgets;
use exface\Core\Interfaces\Widgets\iHaveChildren;
/**
 * The DataColumnEditor is a wrapper widget for inputs and other widget types, that enables them to be used as in-table-editors. It encapsulates
 * special logic additionally needed for in-table-editing and also holds some parameters, that these widgets would not need if used stan alone
 * IDEA: This widget is work-in-progress and currently no used. I like the idea, but for now, I do not see any "special parameters". Perhaps I will
 * come back to it, after implementing the filter widget, which is similar.
 * @author aka
 *
 */
class DataColumnEditor extends AbstractWidget implements iHaveChildren {
	private $editor_widget;

	public function get_editor_widget() {
		return $this->editor_widget;
	}
	
	public function set_editor_widget($value) {
		$this->editor_widget = $value;
	}
	
	/**
	 * Defines an input widget for the editor. Accepts either instantiated widget or a respective UXON description object.
	 * Passing ready made widgets comes in handy, when creating an editor in the code, while passing UXON objects
	 * is an elegant solution when defining a complex widget in UXON:
	 * columns: [
	 * 	{ 	id: 'column_1',
	 *   	editor: {
	 *   		some_editor_property: '...',
	 *   		widget: {
	 *     			widget_type: 'inputNumber',
	 *     			other_params: ...
	 *     		}
	 *  	}
	 * 	},
	 * 	{ ... }
	 * ]
	 * @param ActionInterface|\stdClass $widget_or_uxon_object
	 * @throws UiWidgetException
	 */
	public function set_widget($widget_or_uxon_object) {
		if ($widget_or_uxon_object instanceof AbstractWidget){
			$this->editor_widget = $widget_or_uxon_object;
		} elseif ($widget_or_uxon_object instanceof \stdClass){
			/* TODO create a widget here
			 * 
			 */
		} else {
			throw new UiWidgetException('The set_widget() method of a ColumnEditor accepts either an instantiated input widget or a UXON description object. ' . gettype($widget_or_uxon_object) . ' given for column "' . $this->get_parent()->get_id() . '".');
		}
	}
	
	/**
	 * Indicates, if a specific method is implemented in the column editor or in the underlying abstract widget.
	 * @param string $method_name
	 * @return boolean
	 */
	protected function has_own_method($method_name){
		if (method_exists($this, $method_name)){
			$parent_class = get_parent_class($this);
			if ($parent_class !== false) return !method_exists($parent_class, $method_name);
			return true;
		}
		else return false;
	}
	

}
?>