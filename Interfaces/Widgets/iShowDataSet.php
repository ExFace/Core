<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Data;
use exface\Core\CommonLogic\WidgetLink;
use exface\Core\CommonLogic\UxonObject;

interface iShowDataSet extends WidgetInterface
{

    /**
     *
     * @return Data
     */
    public function getData();

    /**
     *
     * @param \stdClass $uxon_object            
     * @return \exface\Core\Interfaces\Widgets\iShowDataColumn
     */
    public function setData(\stdClass $uxon_object);

    /**
     *
     * @return WidgetLink
     */
    public function getDataWidgetLink();

    /**
     *
     * @param string|UxonObject|WidgetLink $string_or_uxon_or_widget_link            
     */
    public function setDataWidgetLink($string_or_uxon_or_widget_link);
}