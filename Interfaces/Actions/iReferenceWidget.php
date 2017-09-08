<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\WidgetInterface;

interface iReferenceWidget
{

    /**
     *
     * @return WidgetInterface
     */
    public function getWidget();

    /**
     * Returns page id specified with setPageId() or the page_id UXON property
     * respectively or the UID of the target-page, if no id was specified explicitly.
     *
     * @return string
     */
    public function getPageId();
    
    /**
     * The id of the page to get the widget from.
     *
     * Widget links accept the internal UIDs of pages as well as CMS-page ids
     * here because the users do not really know the difference and will attempt
     * to specify the id, they see first. Since most CMS show their internal
     * ids, that typically are not UUIDs, we just allow both ids here. Note,
     * that the method getPageId() will allways return the UID thogh!
     *
     * @param string $value
     * @return iReferenceWidget
     */
    public function setPageId($value);
    
    /**
     * Returns the namespaced alias of the target page.
     *
     * @return string
     */
    public function getPageAlias();
    
    /**
     * Namespaced alias of the page to get the widget from.
     *
     * This is a more comfortable alternative to specifying the page_id as the
     * alias is mostly directly visible in the URL of the page.
     *
     * @param string $value
     * @return iReferenceWidget
     */
    public function setPageAlias($value);
    
    /**
     * @return string
     */
    public function getWidgetId();
    
    /**
     * Specifies the id of the widget to be shown - if not set, the main widget of the
     * page will be used.
     *
     * @param string $value
     * @return iReferenceWidget
     */
    public function setWidgetId($value);
}