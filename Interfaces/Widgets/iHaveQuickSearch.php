<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Input;
use exface\Core\Interfaces\Model\ConditionGroupInterface;

/**
 * This interface describes widgets, that have a quick search field.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveQuickSearch extends WidgetInterface
{
    
    
    public function getQuickSearchPlaceholder() : string;
    
    /**
     *
     * @return bool|NULL
     */
    public function getQuickSearchEnabled() : ?bool;
    
    /**
     * Set to TRUE/FALSE to enable or disable quick search functionality.
     *
     * By default, the facades are free to decide, if quick search should be used
     * for specific data widgets.
     *
     * @uxon-property quick_search_enabled
     * @uxon-type boolean
     *
     * @param bool $value
     * @return iHaveQuickSearch
     */
    public function setQuickSearchEnabled(bool $value) : iHaveQuickSearch;
    
    /**
     *
     * @return Input
     */
    public function getQuickSearchWidget() : Input;
    
    /**
     * Configure the quick-search widget (e.g. to add autosuggest, etc.).
     *
     * @uxon-property quick_search_widget
     * @uxon-type \exface\Core\Widgets\Input
     * @uxon-tempalte {"widget_type": ""}
     *
     * @param UxonObject $value
     * @return iHaveQuickSearch
     */
    public function setQuickSearchWidget(UxonObject $uxon) : iHaveQuickSearch;
    
    /**
     * Retrurns the condition group to be used for quick search using the given value.
     * 
     * By default the $value is NULL, so the condition group would be empty.
     * 
     * @param mixed|NULL $value
     * @return ConditionGroupInterface
     */
    public function getQuickSearchConditionGroup($value = null) : ConditionGroupInterface;
}