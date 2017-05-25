<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

interface iShowText extends iCanBeAligned
{

    /**
     * Returns the text size (one of the EXF_TEXT_SIZE_xxx constants)
     *
     * @return string
     */
    public function getSize();

    /**
     * Sets the text size.
     * Accepts one of the EXF_TEXT_SIZE_xxx constants.
     *
     * @param string $value            
     * @throws WidgetPropertyInvalidValueError
     * @return iShowText
     */
    public function setSize($value);

    /**
     * Returns the text style (one of the EXF_TEXT_STYLE_xxx constants)
     *
     * @return string
     */
    public function getStyle();

    /**
     * Sets the text size.
     * Accepts one of the EXF_TEXT_STYLE_xxx constants.
     *
     * @param string $value            
     * @throws WidgetPropertyInvalidValueError
     * @return iShowText
     */
    public function setStyle($value);
}