<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;

/**
 * Returns a property of the metaobject of the data or any explicitly specified object
 *
 * Examples:
 *
 * - `=ObjectProperty('UID')` will return the UID of the data object.
 * - `=ObjectProperty('ALIAS_WITH_NS')` will return the namespaced alias of the data object.
 * - `=ObjectProperty('APP__ALIAS')` will return the namespace of data object (= alias of the app).
 * - `=ObjectProperty('UID', 'my.App.ObjectAlias')` will return the UID of the specified object.
 * 
 */
class ObjectProperty extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $property = null, string $objectAlias = null)
    {
        if ($objectAlias === null) {
            if ($this->getDataSheet() !== null) {
                $obj = $this->getDataSheet()->getMetaObject();
            } else {
                throw new InvalidArgumentException('Cannot use formula =ObjectProperty() statically without the second argument!');
            }
        } else {
            $obj = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias);
        }

        $property = $property ?? 'NAME';
        switch (mb_strtoupper($property)) {
            case 'NAME': return $obj->getName();
            case 'ALIAS': return $obj->getAlias();
            case 'ALIAS_WITH_NS': return $obj->getAliasWithNamespace();
            case 'NAMESPACE':
            case 'APP__ALIAS': return $obj->getNamespace();
            case 'DATA_ADDRESS': return $obj->getDataAddress();
        }
        
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.OBJECT');
        if ($obj->hasAttribute($property)) {
            $lookupSheet = DataSheetFactory::createFromObject($obj);
            $lookupSheet->getFilters()->addConditionFromString('ALIAS_WITH_NS', $objectAlias, ComparatorDataType::EQUALS);
            $col = $lookupSheet->getColumns()->addFromExpression($property);
            $lookupSheet->dataRead();
            return $col->getValue(0);
        }
        
        throw new InvalidArgumentException('Invalid property "' . $property . '" requested for =ObjectProperty() formula');
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::isStatic()
     */
    public function isStatic() : bool
    {
        return $this->hasArgument(1);
    }
}