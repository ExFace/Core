<?php

namespace exface\Core\Widgets\Traits;

/**
 * This is a convenience trait that adds a new `attribute_group_alias` property to a widget. 
 */
trait iHaveAttributeGroupTrait
{
    protected ?string $attributeGroupAlias = null;

    /**
     * Create multiple similar widgets here: one for every attribute in the group. 
     * 
     * This is more like a template for widgets for attributes from the group. If you only place
     * an `attribute_group_alias` in a widget, it will be replaced by default editor/display widgets
     * of every attribute in the group. If you define other properties (e.g. `widget_type`), they
     * will act as a template and every group widget will get them.
     * 
     * Attribute group aliases may include relation paths and aggregators, just like single attribute
     * aliases.
     * 
     * ## Available attribute groups
     * 
     * In addition to attribute groups defined in the model of the object, there are also some built-in
     * groups:
     * 
     * - `~ALL`
     * - `~VISIBLE`
     * - `~REQUIRED`
     * - `~EDITABLE``
     * - `~DEFAULT_DISPLAY`
     * - `~WRITABLE`
     * - `~READABLE`
     * - `~COPYABLE`
     * - `~CUSTOM`
     * 
     * You can combine multiple attribute groups, like `~VISIBLE~REQUIRED`. This works
     * like filtering attributes with an AND operator. The above group would contain all attributes that
     * are both visible AND required. You can negate a group alias like `~VISIBLE~!REQUIRED`. 
     * This example would select all attributes that are visible AND not required.
     * 
     * ## Customizing each resulting widget
     * 
     * Apart from `attribute_group_alias` you can also define other widget properties on group-level: e.g.
     * `hint` or `disabled_if`, etc. These will be applied to every one of the resulting widgets.
     * 
     * You can use the placeholders `[#~attribute:ALIAS#]` and `[#~attribute:NAME#]` to place attribute
     * information in specific properties of the widget.
     * 
     * ## Examples
     * 
     * Assuming, we have a widget based on the `ORDER` object, we can use the following attribute groups:
     * 
     * - `~DEFAULT_DISPLAY` - attributes of the `ÒRDER`, that are selected for default display
     * - `CUSTOMER__~CUSTOM` - all custom attributes of the customer, that placed the order
     * - `CUSTOMER__my.App.IMPORTANT_ATTRIBUTES` - explicitly defined attribute group of the customer
     * - `ORDER_POS__~DEFAULT_DISPLAY:LIST_DISTINCT` - Lists of default display attributes of the order positions.
     * The `LIST_DISTINCT` aggregator will be applied to every attribute from the group: e.g. `ORDER_POS__NAME:LIST_DISTINCT`,
     * etc.
     * - `~EDITABLE~REQUIRED` - all attributes, that are editable and required`
     * 
     * @uxon-property attribute_group_alias
     * @uxon-type metamodel:attribute_group
     * @uxon-template ~VISIBLE
     *
     * @param string|null $groupAlias
     * @return $this
     */
    public function setAttributeGroupAlias(?string $groupAlias) : static
    {
        $this->attributeGroupAlias = $groupAlias;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAttributeGroupAlias() : ?string
    {
        return $this->attributeGroupAlias;
    }
}