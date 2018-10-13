<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iShowImage extends WidgetInterface
{

    /**
     * Returns source-URI of the image.
     *
     * @return string|NULL
     */
    public function getUri() : ?string;

    /**
     * Sets the source-URI of the image.
     *
     * @param string $value
     * @return iShowImage
     */
    public function setUri(string $value) : iShowImage;
}