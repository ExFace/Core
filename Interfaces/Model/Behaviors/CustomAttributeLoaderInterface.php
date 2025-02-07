<?php

namespace exface\Core\Interfaces\Model\Behaviors;

interface CustomAttributeLoaderInterface
{
    /**
     * Returns the data address prefix for custom attributes loaded by
     * this behavior.
     * 
     * While details depend on the implementation, the data address prefix
     * will likely consist of a regular data address suffixed with a special accessor.
     * For example in `CustomAttributesJsonBehavior`: "json_address::$.".
     * 
     * @return string
     */
    function getCustomAttributeDataAddressPrefix() : string;

    /**
     * Converts a custom attribute storage key to a matching alias.
     * 
     * This usually means stripping any accessors such as "$." and reducing path notations
     * such as "some.pathTo.alias" turning into "alias".
     * 
     * @param string $storageKey
     * @return string
     */
    function customAttributeStorageKeyToAlias(string $storageKey) : string;

    /**
     * Generates a complete custom attribute data address based off of the specified
     * storage key.
     * 
     * @param string $storageKey
     * @return string
     */
    function getCustomAttributeDataAddress(string $storageKey) : string;
}