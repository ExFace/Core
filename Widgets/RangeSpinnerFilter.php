<?php
namespace exface\Core\Widgets;

/**
 * Similar to a RangeFilter with additional +/- buttons to easily "move" the range
 * 
 * ## Examples
 * 
 * This will create a date-range filter, set to the last 7 days by default. The
 * filter will also have buttons to move the range forward/backward by a week.
 *  
 * ```
 *  {
 *      "widget_type": "RangeSpinnerFilter",
 *      "attribute_alias": "DATE",
 *      "value_from": -7,
 *      "value_to": 0,
 *      "value_step: "7d"
 *  }
 *  
 * ```
 *     
 * @author Andrej Kabachnik
 *        
 */
class RangeSpinnerFilter extends RangeFilter
{
    private $valueStep = '1';
    
    /**
     * 
     * @return string
     */
    public function getValueStep() : string
    {
        return $this->valueStep;
    }
    
    /**
     * The step of the spinner: e.g. `1` for number or `8d` for dates
     * 
     * @uxon-property value_step
     * @uxon-type string
     * @uxon-default 1
     * 
     * @param string $value
     * @return RangeSpinnerFilter
     */
    public function setValueStep(string $value) : RangeSpinnerFilter
    {
        $this->valueStep = $value;
        return $this;
    }
}