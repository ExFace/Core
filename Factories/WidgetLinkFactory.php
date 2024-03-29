<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\WidgetLink;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

abstract class WidgetLinkFactory extends AbstractUxonFactory
{
    /**
     * 
     * @param WidgetInterface $sourceWidget
     * @param UxonObject|string $stringOrUxon
     * @return WidgetLinkInterface
     */
    public static function createFromWidget(WidgetInterface $sourceWidget, $stringOrUxon) : WidgetLinkInterface
    {
        if (is_string($stringOrUxon)) {
            $stringOrUxon = ltrim($stringOrUxon, "=");
        }
        return new WidgetLink($sourceWidget->getPage(), $sourceWidget, $stringOrUxon);
    }
    
    /**
     * 
     * @param UiPageInterface $sourcePage
     * @param UxonObject|string $stringOrUxon
     * @return WidgetLinkInterface
     */
    public static function createFromPage(UiPageInterface $sourcePage, $stringOrUxon) : WidgetLinkInterface
    {
        return new WidgetLink($sourcePage, null, $stringOrUxon);
    }
}