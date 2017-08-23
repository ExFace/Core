<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\RuntimeException;

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
     * Returns page id specified with setPageId() or the page_id UXON property
     * respectively or the UID of the target-page, if no id was specified explicitly.
     * 
     * @return string
     */
    public function getPageId();
    
    /**
     * Returns the qualified alias of the page, the link points to.
     * 
     * @return string
     */
    public function getPageAlias();

    /**
     * Returns the target-page of the link.
     *
     * @return UiPageInterface
     */
    public function getPage();

    /**
     * Specifies the target-page for the link via page UID or CMS-id.
     * 
     * Widget links accept the internal UIDs of pages as well as CMS-page ids
     * here because the users do not really know the difference and will attempt
     * to spceify the id, they see first. Since most CMS show their internal
     * ids, that typically are not UUIDs, we just allow both ids here. Note,
     * that the method getPageId() will allways return the UID thogh!
     *
     * @param string $value            
     * 
     * @throws RuntimeException if a page alias is defined too and does not match the id
     * 
     * @return WidgetLinkInterface
     */
    public function setPageId($value);
    
    /**
     * Specifies the target-page for the link via qualified page alias.
     * 
     * @param string $alias_with_namespace
     * 
     * @throws RuntimeException if a page id is defined too and does not match the alias
     * 
     * @return WidgetLinkInterface
     */
    public function setPageAlias($alias_with_namespace);

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