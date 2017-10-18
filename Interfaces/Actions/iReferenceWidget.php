<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

interface iReferenceWidget
{

    /**
     *
     * @return WidgetInterface
     */
    public function getWidget();

    /**
     * Returns the UiPage specified by setPageAlias() or by the page_alias
     * UXON property respectively or the target-page, if no alias was specified
     * explicitly.
     *
     * @return UiPageInterface
     */
    public function getPage();
    
    /**
     * The alias of the page to get the widget from.
     * 
     * Widget links accept the internal UIDs, the namespaced alias as well as 
     * the CMS-page ids here because the users do not really know the difference
     * and will attempt to specify the id, they see first. Since most CMS show
     * their internal ids, that typically are not UUIDs, we just allow both ids
     * here.
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