<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
interface WidgetLinkInterface extends ExfaceClassInterface, iCanBeConvertedToUxon
{

    public function parseLink($string_or_object);

    /**
     * Parse expressions like [page_id]widget_id!column$row
     */
    public function parseLinkString($string);

    /**
     *
     * @return string
     */
    public function getPageId();

    /**
     *
     * @return UiPageInterface
     */
    public function getPage();

    /**
     *
     * @param string $value            
     * @return WidgetLinkInterface
     */
    public function setPageId($value);

    /**
     * Retruns the id of the linked widget within the linked page.
     *
     * If an id space is set, this will return the fully qualified widget id includig the id space.
     *
     * @return string
     */
    public function getWidgetId();

    /**
     *
     * @param string $value            
     * @return WidgetLinkInterface
     */
    public function setWidgetId($value);

    /**
     * Returns the widget instance referenced by this link
     *
     * @throws uiWidgetNotFoundException if no widget with a matching id can be found in the specified resource
     * @return WidgetInterface
     */
    public function getWidget();

    /**
     *
     * @return UxonObject
     */
    public function getWidgetUxon();

    /**
     *
     * @return string
     */
    public function getColumnId();

    /**
     *
     * @param string $value            
     * @return WidgetLinkInterface
     */
    public function setColumnId($value);

    /**
     *
     * @return integer
     */
    public function getRowNumber();

    /**
     *
     * @param integer $value            
     * @return WidgetLinkInterface
     */
    public function setRowNumber($value);

    /**
     * Sets the id space - a subnamespace of the page, for the widget ids to be resolved in.
     *
     * If a page has multiple id spaces, the same widget id can exist in each of the independently.
     *
     * @return string
     */
    public function getWidgetIdSpace();

    /**
     * Returns the id space of this widget link: an empty string by default.
     *
     * @param string $value            
     * @return WidgetLinkInterface
     */
    public function setWidgetIdSpace($value);
}
?>