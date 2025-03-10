<?php

namespace exface\Core\Interfaces\Model\Behaviors;

interface CustomAttributeLoaderInterface
{
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