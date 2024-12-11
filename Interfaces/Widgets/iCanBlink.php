<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;

/**
 * Interface for widgets that has visual elements which can blink.
 * 
 * @author Omer Turan
 *
 */
interface iCanBlink
{
     /**
     * Boolean flag to enable blinking of the shape
     *
     * @param string $value
     */
    public function setIsBlinking(bool $value);


	/**
	 * Returns true if the widget is blinking
	 * 
	 * @return bool
	 */
    public function getIsBlinking() : bool;

    /**
     * 
     * @return DataColumn|NULL
     */
    public function getBlinkingFlagColumn() : ?DataColumn;


    /**
     * Sets the attribute alias of the blinking attribute
     * 
     * @param string $value
     * @return MapLayerInterface
     */
    public function setBlinkingAttribute(string $value) : MapLayerInterface;
}