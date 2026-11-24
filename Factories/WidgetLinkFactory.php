<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\WidgetLink;
use exface\Core\CommonLogic\WidgetLinkCoordinate;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkCoordinateInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Creates widget links and coordinates within their target widgets.
 */
abstract class WidgetLinkFactory extends AbstractUxonFactory
{
    /**
     * Reuses an existing coordinate or parses a string; NULL uses widget defaults.
     *
     * @param string|WidgetLinkCoordinateInterface|null $coordinate
     * @return WidgetLinkCoordinateInterface
     */
    public static function createCoordinateFromAnything(string|WidgetLinkCoordinateInterface|null $coordinate) : WidgetLinkCoordinateInterface
    {
        return $coordinate instanceof WidgetLinkCoordinateInterface
            ? $coordinate : new WidgetLinkCoordinate($coordinate ?? '');
    }

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