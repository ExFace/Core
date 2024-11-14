<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\Interfaces\HtmlCompatibleDataTypeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Converts the given value to HTML, if possible or formats it as with `=Format()`.
 * 
 * You can specify the datatype of the input value, if necessary, like this:
 * 
 * - `=ToHtml(MY_ATTR)` - convert a compatible attribute (e.g. with Markdown data type) to HTML automatically
 * - `=ToHtml(ANY_TEXT, 'exface.Core.Markdown')` - convert any attribute to HTML treating it as Markdown and
 * ignoring its data type in the model
 */
class ToHtml extends Formula
{
    /**
     * @param $value
     * @param $dataTypeAlias
     * @return mixed|string
     */
    public function run($value = null, $dataTypeAlias = null): mixed
    {
        if ($dataTypeAlias) {
            $inputDataType = DataTypeFactory::createFromString($this->getWorkbench(), $dataTypeAlias);
        } else {
            $inputDataType = $this->getArgumentType(0);
        }

        if($inputDataType instanceof HtmlCompatibleDataTypeInterface) {
            return $inputDataType->toHtml($value);
        } else {
            return $value;
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see Formula::getDataType()
     */
    public function getDataType() : DataTypeInterface
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), StringDataType::class);
    }
}