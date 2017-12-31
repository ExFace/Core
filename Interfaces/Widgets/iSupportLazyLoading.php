<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;

/**
 * This interface defines, how widgets can support optional lazy (asynchronous) loading.
 * 
 * Lazy loading widgets trigger a secondary action asynchronously when being instantiated.
 * This action actually does the lazy loading. Depending on the widget and the currently
 * used template, the action can load different parts of the widget: e.g. the data in
 * lazy data widgets or the entire widget itself for panels or dialogs.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iSupportLazyLoading extends iTriggerAction
{

    /**
     * 
     * @return boolean|null
     */
    public function getLazyLoading();

    /**
     * 
     * @param boolean $value
     * @return iSupportLazyLoading
     */
    public function setLazyLoading($value);

    /**
     * 
     * @throws WidgetPropertyNotSetError
     * @return string
     */
    public function getLazyLoadingActionAlias();

    /**
     * 
     * @param string $value
     * @return iSupportLazyLoading
     */
    public function setLazyLoadingActionAlias($value);

    /**
     * @return string
     */
    public function getLazyLoadingGroupId();

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
     * templates, consequently the behavior of such a group might vary in the different
     * templates.
     *
     * @uxon-property lazy_loading_group_id
     * @uxon-type string
     *
     * @param string $value            
     */
    public function setLazyLoadingGroupId($value);
}