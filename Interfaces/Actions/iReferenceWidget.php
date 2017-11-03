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
    
    /**
     * Returns the fully qualified alias of the UI page with the referenced widget.
     * 
     * @param string $alias_with_namespace
     * @return iReferenceWidget
     */
    public function setPageAlias($alias_with_namespace);
}