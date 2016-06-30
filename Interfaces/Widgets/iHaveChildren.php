<?php
namespace exface\Core\Interfaces\Widgets;
interface iHaveChildren {
	/**
	 * Returns all child elements of this widget as an array, regardless of what they do.
	 * @return AbstractWidget[]
	 */
	public function get_children();
}