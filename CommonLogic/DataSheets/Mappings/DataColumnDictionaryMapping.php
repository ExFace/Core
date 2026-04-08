<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\CommonLogic\UxonObject;

/**
 * Maps one data sheet column to another translating all values using a dictionary.
 * 
 * @see DataColumnMapping
 * 
 * @author Andrej Kabachnik
 *
 */
class DataColumnDictionaryMapping extends DataColumnMapping
{
    private array $dictionary = [];

    /**
     * 
     * @see DataColumnMapping::mapValue()
     */
    protected function mapValue($fromValue)
    {
        if ($fromValue === null || $fromValue === '') {
            return $fromValue;
        }
        return $this->dictionary[$fromValue] ?? $fromValue;
    }

    /**
     * 
     * @see DataColumnMapping::mapValues()
     */
    protected function mapValues(array $fromValues) : array
    {
        $toValues = [];
        foreach ($fromValues as $fromValue) {
            $toValues[] = $this->mapValue($fromValue);
        }
        return $toValues;
    }

    /**
     * 
     * @param UxonObject $valueMap
     * @return DataColumnDictionaryMapping
     */
    protected function setDictionary(UxonObject $valueMap) : DataColumnDictionaryMapping
    {
        $this->dictionary = $valueMap->toArray();
        return $this;
    }
}