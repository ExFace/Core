<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Parses (almost) any value into a date and time in the internal format or a given ICU format.
 *
 * The first parameter is the value to parse, while the second (optional) parameter is
 * the ICU date format or `locale`/`locale_with_seconds` for the default format of the current 
 * language. Additionally the source format can be defined in the third parameter if it cannot 
 * be parsed automatically.
 *
 * Examples:
 *
 * - `=DateTime('25.03.2020 21:00:55')` = 2020-03-25 21:00:55
 * - `=DateTime('1585090800')` = 2020-03-25 00:00:00
 * - `=DateTime('1585090800', 'dd.MM.yyyy HH:mm:ss)` = 25.03.2020 00:00:00
 * - `=DateTime('2020-03-25 21:00:55', 'yyyyMMddHHmmss')` = 20200325210055
 * 
 * See https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax for a complete guide to
 * the ICU date format syntax.
 *
 * @link https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
 */
class DateTime extends Date
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Formulas\Date::run()
     */
    public function run($date = null, $returnFormat = DateTimeDataType::DATETIME_ICU_FORMAT_INTERNAL, $inputFormat = null)
    {
        switch (true) {
            case $returnFormat === null:
                $returnFormat = DateTimeDataType::DATE_ICU_FORMAT_INTERNAL;
                break;
            case $returnFormat === 'locale':
                $returnFormat = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
                break;
            case $returnFormat === 'locale_with_seconds':
                $returnFormat = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
                break;
        }
        
        return parent::run($date, $returnFormat, $inputFormat);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
    }
}
?>