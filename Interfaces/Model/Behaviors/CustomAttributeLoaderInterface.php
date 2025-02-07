<?php

namespace exface\Core\Interfaces\Model\Behaviors;

interface CustomAttributeLoaderInterface
{
    function getCustomAttributeDataAddressPrefix() : string;
    function customAttributeStorageKeyToAlias(string $storageKey) : string;
    function getCustomAttributeDataAddress(string $storageKey) : string;
}