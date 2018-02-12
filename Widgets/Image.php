<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;

/**
 * The image widget shows the image specified by the URL in the value of an attribute.
 *
 * @author Andrej Kabachnik
 *        
 */
class Image extends Display implements iCanBeAligned
{
    use iCanBeAlignedTrait;
    
    /**
     * 
     * @return string|NULL
     */
    public function getUri()
    {
        return $this->getValue();
    }

    /**
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Image
     */
    public function setUri($value)
    {
        return $this->setValue($value);
    }
}
?>