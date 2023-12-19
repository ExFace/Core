<?php
namespace exface\Core\Widgets;

/**
 * A filter with additional +/- buttons to easily "move" the numbers or dates
 * 
 * ## Examples
 * 
 * This will create a date filter, set to today by default. The filter will also 
 * have buttons to move the date forward/backward by a day.
 *  
 * ```
 *  {
 *      "widget_type": "SpinnerFilter",
 *      "attribute_alias": "DATE",
 *      "value": 0,
 *      "value_step: "1d"
 *  }
 *  
 * ```
 *     
 * @author Andrej Kabachnik
 *        
 */
class SpinnerFilter extends Filter
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
    public function setValueStep(string $value) : SpinnerFilter
    {
        $this->valueStep = $value;
        return $this;
    }
}