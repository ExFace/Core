<?php

namespace exface\Core\Widgets\Traits;

/**
 * This is a convenience trait that adds a new `attribute_group_alias` property to a widget. 
 * Make sure to override `setAttributeGroupAlias(string)` with whatever logic you need.
 */
trait ISupportAttributeGroupsTrait
{
    protected ?string $attributeGroupAlias = null;

    /**
     * Declare an entire group of attributes. For each attribute contained in this group,
     * a matching widget will be generated automatically.
     * 
     * You can concatenate multiple attribute groups, like `~VISIBLE~REQUIRED`. This functions
     * like filtering them with an AND operator. The above group would contain all attributes that
     * are both visible AND required.
     * 
     * You can negate a group alias like `~VISIBLE~!REQUIRED`. This example would select all attributes
     * that are visible AND not required.
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