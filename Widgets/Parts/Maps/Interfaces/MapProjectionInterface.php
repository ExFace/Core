<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface MapProjectionInterface extends iCanBeConvertedToUxon
{    
    /**
     *
     * @return string
     */
    public function getName() : string;
}