<?php
namespace exface\Widgets;
use exface\Core\Interfaces\Widgets\iAmResizable;
use exface\Core\Interfaces\Widgets\iHaveBorders;
/**
 * A SplitPanel is a special panel, which can be resized within a split layout (e.g. by dragging it's
 * internal border).
 * @author PATRIOT
 */
class SplitPanel extends Panel implements iAmResizable, iHaveBorders {
	/** @var boolean makes it possible to resize this panel within the layout */
	private $resizable = true;
	private $show_border = true;
	
	public function get_resizable() {
		return $this->resizable;
	}
	
	/**
	 * Makes it possible to resize this panel within the layout if set to true. Resizing one panel
	 * will generally also resize a neighbour panel, since the total size of the layout remains.
	 * @param boolean $value
	 */
	public function set_resizable($value) {
		$this->resizable = $value;
	}  
	
	public function get_show_border() {
		return $this->show_border;
	}
	
	public function set_show_border($value) {
		$this->show_border = $value;
	}	  
}
?>