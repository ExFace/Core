<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;

/**
 * Returns the name of an object from the metamodel
 *
 * Examples:
 * 
 * - `=ObjectName('my.App.ObjectAlias')` will return the Object Name of that object.
 * If there are translations for attribute names, the formula will pick the translation for the
 * current session language.
 * 
 */
class ObjectName extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $objectAlias = null)
    {
        if ($objectAlias === null) {
            throw new InvalidArgumentException('Object alias cannot be null.');
        }

        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias);
        return $obj->getName();
    }
}
