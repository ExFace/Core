<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;

/**
 * Returns any property of an attribute from the metamodel
 * 
 * Technically, attribute properties are attributes of the object `exface.Core.ATTRIBUTE`,
 * so you can request any of those here: `NAME`, `SHORT_DESCRIPTION`, `DEFAULT_VALUE`, or
 * even `DATATYPE__NAME`.
 * 
 * Examples:
 * 
 * - `=AttributeProp('my.App.SOMEOBJECT', 'TITLE')` will return the name of the `TITLE` attribute.
 * If there are translations for attribute names, the formula will pick the translation for the
 * current session language.
 * - `=AttributeProp('my.App.SOMEOBJECT', 'DEFAULT_VALUE')` will return the default value for the
 * attribute. If the default value is a formula, it will be evaluated.
 * 
 */
class AttributeProp extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $objectAlias = null, string $attributeAlias = null, string $property = 'NAME')
    {
        if (! $objectAlias || $attributeAlias === null || $attributeAlias === '') {
            return null;
        }
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias);
        switch ($property) {
            case 'NAME': 
                $val = $obj->getAttribute($attributeAlias)->getName(); 
                break;
            case 'SHORT_DESCRIPTION':
            case 'HINT': 
                $val = $obj->getAttribute($attributeAlias)->getShortDescription(); 
                break;
            case 'DEFAULT_VALUE':
                $val = $obj->getAttribute($attributeAlias)->getDefaultValue()->evaluate();
                break;
            case 'FIXED_VALUE':
                $val = $obj->getAttribute($attributeAlias)->getFixedValue()->evaluate();
                break;
            default:
                $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.ATTRIBUTE');
                $ds->getFilters()->addConditionFromString('OBJECT__ALIAS_WITH_NS', $objectAlias, ComparatorDataType::EQUALS);
                $ds->getFilters()->addConditionFromString('ALIAS', $attributeAlias, ComparatorDataType::EQUALS);
                $col = $ds->getColumns()->addFromExpression($property);
                $ds->dataRead();
                $val = $col->getValue(0);
        }
        return $val;
    }
}
