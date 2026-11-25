<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Formats a duration in milliseconds, automatically switching to seconds above 1000 ms.
 * 
 * The optional second argument controls rounding, with one decimal place by default.
 * Empty values return an empty string.
 * 
 * Examples:
 * 
 * - `=FormatMs(125)` => "125 ms"
 * - `=FormatMs(1250)` => "1.3 s"
 * - `=FormatMs(1250, 2)` => "1.25 s"
 * - `=FormatMs(DURATION_MS)` formats a duration attribute.
 */
class FormatMs extends Formula
{
    /**
     * Formats milliseconds using the shared time formatter.
     * 
     * @param float|int|string|null $milliseconds
     * @param int $decimals
     * @return string
     * @throws FormulaError
     */
    public function run($milliseconds = null, int $decimals = 1) : string
    {
        if ($milliseconds === null || $milliseconds === '') {
            return '';
        }

        if (!is_numeric($milliseconds) || !is_finite((float) $milliseconds)) {
            throw new FormulaError($this, 'FormatMs() expects a finite number of milliseconds.');
        }

        return TimeDataType::formatMs((float) $milliseconds, $decimals);
    }

    /**
     * {@inheritDoc}
     * @see Formula::getDataType()
     */
    public function getDataType() : DataTypeInterface
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), StringDataType::class);
    }
}
