<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\ICanBeConvertedToHtml;

/**
 * Converts the given value to HTML, if possible or formats it as with `=Format()`.
 * 
 * You can specify the datatype of the input value, if necessary, like so:
 * `=ToHtml(value, 'DATATYPE')`, for example: `[#=ToHtml(placeholder, 'exface.Core.MarkdownDataType')#]`
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
        $inputDataType = $this->getInputDataType($dataTypeAlias);
        if($inputDataType instanceof ICanBeConvertedToHtml) {
            return $inputDataType->toHtml($value);
        } else {
            return $inputDataType->format($value);
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