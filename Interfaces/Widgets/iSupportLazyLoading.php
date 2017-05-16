<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Interfaces\WidgetInterface;

interface iSupportLazyLoading extends WidgetInterface {
	public function get_lazy_loading();
	public function set_lazy_loading($value);
	public function get_lazy_loading_action();
	public function set_lazy_loading_action($value);
	public function get_lazy_loading_group_id();
	
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
     * Widgets inside a lazy loading group are not allowed to have value-references at
     * all (doesn't matter if the other elements is a member of the same group or not).
     * They are allowed to have filter-references to other elements of the same group but
     * not to other elements outside of this group. If such a reference is needed it may
     * be a good idea to include this widget in the lazy loading group.
     * 
     * On the other hand it is not a problem if widgets outside the group have a value-
     * or filter-reference to a widget inside the group. This is actually the intended
     * usage of a lazy loading group.
     * 
     * @uxon-property lazy_loading_group_id
	 * @uxon-type string
	 * 
	 * @param string $value
	 */
	public function set_lazy_loading_group_id($value);
}