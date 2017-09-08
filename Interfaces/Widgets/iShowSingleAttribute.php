<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\WidgetInterface;

interface iShowSingleAttribute extends WidgetInterface
{

    /**
     *
     * @return MetaAttributeInterface
     */
    public function getAttribute();

    /**
     *
     * @return string
     */
    public function getAttributeAlias();
}