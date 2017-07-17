<?php
namespace exface\Core\CommonLogic;

class WidgetDimension
{

    private $exface;

    private $value = NULL;

    function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * Parses a dimension string.
     * Dimensions may be specified in relative ExFace units (in this case, the value is numeric)
     * or in any unit compatible with the current template (in this case, the value is alphanumeric because the unit must be
     * specified directltly).
     *
     * How much a relative unit really is, depends on the template. E.g. a relative height of 1 would mean, that the widget
     * occupies on visual line in the template (like a simple input), while a relative height of 2 would span the widget over
     * two lines, etc. The same goes for widths.
     *
     * Examples:
     * - "1" - relative height of 1 (e.g. a simple input widget). The template would need to translate this into a specific height like 300px or similar.
     * - "2" - double relative height (an input with double height).
     * - "0.5" - half relative height (an input with half height)
     * - "300px" - template specific height defined via the CSS unit "px". This is only compatible with templates, that understand CSS!
     * - "100%" - percentual height. Most templates will support this directly, while others will transform it to relative or template specific units.
     */
    public function parseDimension($string)
    {
        $this->setValue(trim($string));
    }

    public function toString()
    {
        return $this->value;
    }

    public function getValue()
    {
        return $this->value;
    }

    private function setValue($value)
    {
        $this->value = ($value === '') ? null : $value;
        return $this;
    }

    /**
     * Returns TRUE if the dimension is not defined (null) or FALSE otherwise.
     *
     * @return boolean
     */
    public function isUndefined()
    {
        if (is_null($this->getValue()))
            return true;
        else
            return false;
    }

    /**
     * Returns TRUE if the dimension was specified in relative units and FALSE otherwise.
     *
     * @return boolean
     */
    public function isRelative()
    {
        if (! $this->isUndefined() && (is_numeric($this->getValue()) || $this->getValue() == 'max'))
            return true;
        else
            return false;
    }

    /**
     * Returns TRUE if the dimension was specified in relative units and equals 'max' and
     * FALSE otherwise.
     *
     * @return boolean
     */
    public function isMax()
    {
        if (! $this->isUndefined() && (strcasecmp($this->getValue(), 'max') == 0)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns TRUE if the dimension was specified in template specific units and FALSE otherwise.
     *
     * @return boolean
     */
    public function isTemplateSpecific()
    {
        if (! $this->isUndefined() && ! $this->isPercentual() && ! $this->isRelative())
            return true;
        else
            return false;
    }

    /**
     * Returns TRUE if the dimension was specified in percent and FALSE otherwise.
     *
     * @return boolean
     */
    public function isPercentual()
    {
        if (! $this->isUndefined() && substr($this->getValue(), - 1) == '%')
            return true;
        else
            return false;
    }
}
?>