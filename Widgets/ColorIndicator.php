<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Exceptions\Model\ConditionIncompleteError;
use exface\Core\Factories\ExpressionFactory;

/**
 * A ColorIndicator will change it's color depending on conditons specified in it's configuration.
 * 
 * Colors are defined as a set of conditions like this:
 * {
 *  "widget_type": "ColorIndicator",
 *  "color_conditions": {
 *      "< 0": "red",
 *      "== 0": "yellow",
 *      "> 0": "green",
 *      "": "red"
 *  }
 * }
 * 
 * In this case, the conditions will be evaluated against the current value of the widget - so
 * the right sight of the condition may be ommitted. Note, that multiple conditions can result 
 * in the same color. In the above example negative values will be colored red as well as empty 
 * values.
 * 
 * Using a widget link on the right side of the condition will make the condition dynamic. This
 * is usefull to compare multiple widgets: e.g. two columns in a DataTable like in the following
 * example:
 * 
 * {
 *  "widget_type": "ColorIndicator",
 *  "color_conditions": {
 *      "< self!data_column": "red",
 *      "== self!data_column": "yellow",
 *      "> self!data_column": "green"
 *  }
 * }
 * 
 * Last but not least, it is possible to use the value of another widget for comparison: i.e.
 * become red if widget X hax a value of Y or if the value of widget X is less than that of
 * widget Y.
 * 
 * @author Andrej Kabachnik
 *
 */
class ColorIndicator extends Display implements iHaveColor
{
    private $fixedColor = null;
    
    private $colorConditions = [];
    
    private $colorConditionsColors = [];
    
    private $colorConditionsUxon = null;
    
    private $fill = true;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::getColor()
     */
    public function getColor()
    {
        // TODO determine color by evaluating conditions with the current value
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->fixedColor = $color;
        return $this;
    }
    
    /**
     * Returns all conditions as a sequential array.
     * 
     * @return Condition[]
     */
    public function getColorConditions()
    {
        if (empty($this->colorConditions) && ! is_null($this->colorConditionsUxon)) {
            foreach ($this->colorConditionsUxon as $cond => $color) {
                if (is_string($cond)) {
                    try {
                        $condition = ConditionFactory::createFromString($this->getWorkbench(), $cond, $this->getMetaObject());
                    } catch (ConditionIncompleteError $e) {
                        $condition = ConditionFactory::createFromStringRelativeToExpression(ExpressionFactory::createFromString($this->getWorkbench(), $this->getAttributeAlias(), $this->getMetaObject()), $cond);
                    }
                } elseif ($cond instanceof UxonObject) {
                    $condition = ConditionFactory::createFromUxon($this->getWorkbench(), $cond);
                }
                $this->colorConditions[] = $condition;
                $this->colorConditionsColors[] = $color;
            }
        }
        return $this->colorConditions;
    }

    /**
     * Defines the set of colors and corresponding conditions.
     * 
     * This property accepts an object with condition strings on the left side
     * and corresponding colors on the right.
     * 
     * Example:
     * {
     *  "widget_type": "ColorIndicator",
     *  "color_conditions": {
     *      "< 0": "red",
     *      "== 0": "yellow",
     *      "> 0": "green",
     *      "": "red"
     *  }
     * }
     * 
     * @uxon-property color_conditions
     * @uxon-type object
     * 
     * @param UxonObject $uxon
     * @return ColorIndicator
     */
    public function setColorConditions(UxonObject $uxon)
    {
        $this->colorConditionsUxon = $uxon;
        return $this;
    }
    
    /**
     * Returns the color to be used if the given condition evaluates to TRUE.
     * 
     * @param Condition $condition
     * @return string
     */
    public function getColorOfCondition(Condition $condition) {
        return $this->colorConditionsColors[array_search($condition, $this->getColorConditions())];
    }

    /**
     * @return boolean
     */
    public function getFill()
    {
        return $this->fill;
    }

    /**
     * Set to TRUE to only color the value of the widget instead of filling it with color.
     * 
     * @uxon-property fill
     * @uxon-type boolean
     * 
     * @param boolean $trueOrFalse
     * @return ColorIndicator
     */
    public function setFill($trueOrFalse)
    {
        $this->fill = BooleanDataType::cast($trueOrFalse);
        return $this;
    }


    
}