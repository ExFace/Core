<?php

namespace exface\Core\DataTypes\Interfaces;

/**
 * Data types with this interface can be converted to HTML
 * 
 * @author Georg Bieger
 */
interface HtmlCompatibleDataTypeInterface
{
    /**
     * Converts the value of this instance to HTML.
     *
     * @param mixed $value
     * @return string
     */
    public function toHtml($value = null) : string;
}