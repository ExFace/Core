<?php
namespace exface\Core\Widgets;

/**
 * The image widget shows the image specified by the URL in the value of an attribute.
 *
 * @author Andrej Kabachnik
 *        
 */
class Image extends Text
{

    public function getUri()
    {
        return $this->getValue();
    }

    public function setUri($value)
    {
        return $this->setValue($value);
    }
}
?>