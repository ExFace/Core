<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;

/**
 * This interface describes widgets and wiget parts, that have a outline color: Shapes in charts, etc
 * 
 * @author Omer Turan
 *
 */
interface iHaveColorWithOutline extends iHaveColor
{
	/**
     * Returns the outline scale color of the widget
     * 
     * @return array
     */
    public function getColorOutlineScale() : ?array;

 /**
     * Specify a custom color scale for the outer border of the shape.
     *
     * @param UxonObject $value
     * @return MapLayerInterface
     */
    public function setColorOutlineScale(UxonObject $value) : MapLayerInterface;


	/**
	 * Returns true if the widget has a color outline scale
	 * 
	 * @return bool
	 */
	public function hasColorOutlineScale() : bool;

	/**
	 * Returns true if the widget has a color outline scale range based
	 * 
	 * @return bool
	 */
	public function isColorOutlineScaleRangeBased() : bool;

	/** 
	 * Returns the outline column of the widget
	 * 
     * @return DataColumn|NULL
	*/
	public function getColorOutlineColumn() : ?DataColumn;
}