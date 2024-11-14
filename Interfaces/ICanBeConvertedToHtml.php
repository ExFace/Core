<?php

namespace exface\Core\Interfaces;

/**
 * This instance can convert its value to HTML.
 */
interface ICanBeConvertedToHtml
{
    /**
     * Converts the value of this instance to HTML.
     *
     * @param null $value
     * @return string
     */
    function toHtml($value = null) : string;
}