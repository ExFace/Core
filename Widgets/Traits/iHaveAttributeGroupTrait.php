<?php

namespace exface\Core\Widgets\Traits;

/**
 * This is a convenience trait that adds a new `attribute_group_alias` property to a widget. 
 * 
 * NOTE: Avoid overriding the setter directly, as that would override the auto-suggest configuration as well.
 * If you want to perform logic whenever the group alias is set, override `onSetAttributeGroupAlias(string)` instead.
 */
trait iHaveAttributeGroupTrait
{
    protected ?string $attributeGroupAlias = null;

    /**
     * Declare an entire group of attributes. 
     * 
     * For each attribute contained in this group, a matching widget will be generated automatically.
     * 
     * You can concatenate multiple attribute groups, like `~VISIBLE~REQUIRED`. This functions
     * like filtering them with an AND operator. The above group would contain all attributes that
     * are both visible AND required. You can negate a group alias like `~VISIBLE~!REQUIRED`. 
     * This example would select all attributes that are visible AND not required.
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
        $this->attributeGroupAlias = $this->onSetAttributeGroupAlias($groupAlias);
        return $this;
    }

    /**
     * Override this function, if you want to decorate your setter with logic.
     * 
     * @param string|null $groupAlias
     * @return string
     */
    protected function onSetAttributeGroupAlias(?string $groupAlias) : string
    {
        return $groupAlias;
    }

    /**
     * @return string|null
     */
    public function getAttributeGroupAlias() : ?string
    {
        return $this->attributeGroupAlias;
    }
}