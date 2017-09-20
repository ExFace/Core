<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\NumberDataType;

/**
 * Creates an HTML-Progressbar taking a value, an optional text and some customization-options (a min-max-range and a color map) as parameters.
 *
 * The value is used to determine the progress, the text will be displayed on-top of the progress bar. If the text is empty, the value will
 * be displayed. The value must start with a number! Non-numeric characters following the number will be ignored. Thus, alphanumeric values
 * like "80%" may be used (resulting in value=80 and text="80%").
 *
 * The color map will paint the progress bar in different colors depending on the value. The following example (assuming min=0 and max=99)
 * would make values between 0 and 30 green, then upto 79 yellow, value 80 would be red and all the following values gill be grey. Colors
 * can be defined in any format compatible with the CSS color attribute.
 * [
 * 30: "#0a0",
 * 79: "yellow",
 * 80: "red",
 * 99: "grey"
 * ]
 *
 * @author Andrej Kabachnik
 *        
 */
class Progressbar extends Formula
{

    /**
     *
     * @param int $value            
     * @param string $text            
     * @param int $min            
     * @param int $max            
     * @param array $colorMap            
     *
     * @return string
     */
    function run($value, $text = '', $min = 0, $max = 100, array $colorMap = null)
    {
        if (! $value){
            return '';
        }
        if (! $text){
            $text = $value;
        }
        if (is_null($colorMap)){
            $colorMap = $this->getColorMapPercentual();
        }
        $value = NumberDataType::parse($value);
        
        $return = '<div style="width:100%; border:1px solid #ccc; position:relative; overflow: hidden">' . '<div style="width:' . ($value ? $value / ($max - $min) * 100 : $min) . '%;background:' . $this->getBackgroundColor($value, $colorMap) . ';">&nbsp;</div>' . '<div style="position:absolute; left:0; top:0; z-index:100; padding:0 0; width:100%">' . $text . '</div>' . '</div>';
        return $return;
    }

    /**
     * Returns color corresponding to $value.
     *
     * @param int $value            
     * @param array $colorMap            
     *
     * @return string
     */
    protected function getBackgroundColor($value, $colorMap)
    {
        return $colorMap[$this->getBackgroundColorKey($value, $colorMap)];
    }

    /**
     * Find the matching colorMap key.
     *
     * @param int $value            
     * @param array $colorMap            
     *
     * @return int
     */
    protected function getBackgroundColorKey($value, $colorMap)
    {
        $colorKeys = array_keys($colorMap);
        asort($colorKeys);
        foreach ($colorKeys as $colorKey) {
            if ($value <= $colorKey)
                return $colorKey;
        }
        
        return max($colorKeys);
    }
    
    protected function getColorMapPercentual(){
        return array(
            10 => "#FFEF9C",
            20 => "#EEEA99",
            30 => "#DDE595",
            40 => "#CBDF91",
            50 => "#BADA8E",
            60 => "#A9D48A",
            70 => "#97CF86",
            80 => "#86C983",
            90 => "#75C47F",
            100 => "#63BE7B");
    }
}

?>