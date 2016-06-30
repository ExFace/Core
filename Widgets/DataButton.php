<?php
namespace exface\Core\Widgets;
/**
 * A special type of button to use in DataTables and other data widgets. Additionally to the normal button functionality
 * this button can be assigned as a click action. So, if a button is bound to a double click, it's action will be called
 * if the user doubleclicks a data element. In theory multiple buttons can be bound to a click action - if so, the template
 * should show a popup menu for this action.
 * @author PATRIOT
 *
 */
class DataButton extends Button {
	private $bind_to_mouse_action = null;
	
	/**
	 * Returns the mouse action, this button is bound to (one of the EXF_MOUSE_ACTION_*** constants) or NULL if the button
	 * is not bound to any mouse action.
	 * @return string
	 */
	public function get_bind_to_mouse_action() {
		return $this->bind_to_mouse_action;
	}
	
	/**
	 * Binds the button to a specific mouse action (like a double click). Accepts one of the EXF_MOUSE_ACTION_*** constants
	 * @param string $mouse_action_name
	 * @return DataButton
	 */
	public function set_bind_to_mouse_action($mouse_action_name) {
		$this->bind_to_mouse_action = $mouse_action_name;
		return $this;
	}  
	
	/**
	 * This a shortcut for set_bind_to_mouse_action(EXF_MOUSE_ACTION_DOUBLE_CLICK), which makes it possible to use easy-to-
	 * understand UXON-attributes
	 * @see set_bind_to_mouse_action()
	 * @return DataButton
	 */
	public function set_bind_to_double_click($value){
		if ($value){
			$this->set_bind_to_mouse_action(EXF_MOUSE_ACTION_DOUBLE_CLICK);
		}
		return $this;
	}
	
	/**
	 * This a shortcut for set_bind_to_mouse_action(EXF_MOUSE_ACTION_RIGHT_CLICK), which makes it possible to use easy-to-
	 * understand UXON-attributes
	 * @see set_bind_to_mouse_action()
	 * @return DataButton
	 */
	public function set_bind_to_right_click($value){
		if ($value){
			$this->set_bind_to_mouse_action(EXF_MOUSE_ACTION_RIGHT_CLICK);
		}
		return $this;
	}
	
	/**
	 * This a shortcut for set_bind_to_mouse_action(EXF_MOUSE_ACTION_DOUBLE_CLICK), which makes it possible to use easy-to-
	 * understand UXON-attributes
	 * @see set_bind_to_mouse_action()
	 * @return DataButton
	 */
	public function set_bind_to_left_click($value){
		if ($value){
			$this->set_bind_to_mouse_action(EXF_MOUSE_ACTION_LEFT_CLICK);
		}
		return $this;
	}
}
?>