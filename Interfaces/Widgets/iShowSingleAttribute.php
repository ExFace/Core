<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\WidgetInterface;

interface iShowSingleAttribute extends WidgetInterface
{

    /**
     *
     * @return Attribute
     */
    public function getAttribute();

    /**
     *
     * @return string
     */
    public function getAttributeAlias();
}