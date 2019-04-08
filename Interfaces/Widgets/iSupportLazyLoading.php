<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\ActionInterface;

/**
 * This interface defines, how widgets can support optional lazy (asynchronous) loading.
 * 
 * Lazy loading widgets trigger a secondary action asynchronously when being instantiated.
 * This action actually does the lazy loading. Depending on the widget and, the action can 
 * load different parts of the widget: e.g. the data in lazy data widgets or the widget's
 * children for panels or dialogs.
 * 
 * Lazy loading can be switched on or off using the `lazy_loading` UXON property. The default
 * setting depends on the facade used.
 * 
 * The lazy loading action can be fully configured in the `lazy_loading_action` property.
 * 
 * Additionally widgets, that depend upon each other can be put into lazy loading groups 
 * to synchronize their behavior and avoid unnecessarry calls to the back-end - see
 * description of `lazy_loading_group_id` for more details.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iSupportLazyLoading extends iTriggerAction
{

    /**
     * 
     * @param bool $default
     * @return bool
     */
    public function getLazyLoading($default = true) : bool;

    /**
     * 
     * @param boolean $value
     * @return iSupportLazyLoading
     */
    public function setLazyLoading(bool $value) : iSupportLazyLoading;
    
    /**
     * 
     * @return ActionInterface
     */
    public function getLazyLoadingAction() : ActionInterface;
    
    /**
     * 
     * @param UxonObject $uxon
     * @return iSupportLazyLoading
     */
    public function setLazyLoadingAction(UxonObject $uxon) : iSupportLazyLoading;

    /**
     * 
     * @return string|NULL
     */
    public function getLazyLoadingGroupId() : ?string;

    /**
     * Assigns this widget to the specified lazy loading group.
     *
     * A lazy loading group is a group of widgets, which is designed to always have a
     * consistent state. Additionally the number of POST-requests, necessary to update
     * this group is optimized. A good example for a lazy loading group are the widgets
     * for STYLE (ARTICLE), ARTICLE_SUPPLIER, COLOR, SIZING and SELLING_CODE which can be
     * added to a group 'article_depend_control'. Then the five widgets will always show
     * a consistent state i.e. if a color is selected only Styles, Article Supplier,
     * Sizings and Selling Codes matching this color are shown.
     *
     * A lazy loading group is created by assigning the same lazy_loading_group_id to all
     * elements of the group. Additionally every element of the group needs a filter-ref-
     * erence to every other element of the group i.e. color will have filter-references
     * for Style, Article Supplier, Sizing and Selling Code. An example for a lazy
     * loading group can be found in the consumer complaint dialog.
     *
     * The concrete implementation of the lazy_loading_group is done in the individual
     * facades, consequently the behavior of such a group might vary in the different
     * facades.
     *
     * @uxon-property lazy_loading_group_id
     * @uxon-type string
     *
     * @param string $value    
     * @return iSupportLazyLoading  
     */
    public function setLazyLoadingGroupId(string $value) : iSupportLazyLoading;
}