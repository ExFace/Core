<?php
namespace exface\Core\Formulas;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\Model\Formula;

/**
 * Parses (almost) any value into a date in the internal format or a given ICU format.
 * 
 * The first parameter is the value to parse, while the second (optional) parameter is
 * the ICU date format or `locale` for the current language format. Additionall the source 
 * format can be defined in the third parameter if it cannot be parsed automatically.
 * 
 * Examples:
 * 
 * - `=Date('25.03.2020 21:00:55')` = 2020-03-25
 * - `=Date('1585090800')` = 2020-03-25
 * - `=Date('1585090800', 'dd.MM.yyyy')` = 25.03.2020
 * - `=Date('2020-03-25', 'yyyyMMddHHmmss')` = 20200325000000
 * - `=Date('2021-07-08', 'E')` = Thu
 * - `=Date('2021-07-08', 'locale')` = 08.07.2021 - depending on the date format set for the current language
 * - `=Date('25.03.20', null, 'dd.MM.yy')` = 2020-03-25
 * 
 * See http://userguide.icu-project.org/formatparse/datetime for a complete guide to
 * the ICU date format syntax.
 *
 * @link http://userguide.icu-project.org/formatparse/datetime
 */
class Date extends Formula
{
    /**
     * 
     * @param string|NULL $date
     * @param string|NULL $returnFormat
     * @param string|NULL $inputFormat
     * @return string
     */
    public function run($date = null, $returnFormat = DateDataType::DATE_ICU_FORMAT_INTERNAL, $inputFormat = null)
    {
        if ($date === null || $date === '') {
            return null;
        }
        
        switch (true) {
            case $returnFormat === null:
                $returnFormat = DateDataType::DATE_ICU_FORMAT_INTERNAL;
                break;
            case $returnFormat === 'locale':
                $returnFormat = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT');
                break;
        }
        return $this->formatDate($date, $returnFormat, $inputFormat);
    }

    /**
     * 
     * @param string|NULL $date
     * @param string $formatTo
     * @param string $formatFrom
     * @return string
     */
    protected function formatDate($date, string $formatTo, string $formatFrom = null) : string
    {
        $formatTo = $this->sanitizeFormat($formatTo);
        
        $dataType = $this->getDataType();
        $dataType->setFormat($formatTo);
        
        if ($formatFrom !== null) {
            $phpDate = DateDataType::castFromFormat($date, $formatFrom, $dataType->getLocale(), true);
        } else {
            $phpDate = $dataType::castToPhpDate($date);
        }
        try {
            return $dataType->formatDate($phpDate);
        } catch (DataTypeCastingError $e) {
            return $date;
        }
    }
    
    /**
     * 
     * @param string $formatArgument
     * @return string|NULL
     */
    protected function sanitizeFormat(string $formatArgument) : ?string
    {
        $format = $formatArgument;
        if ($format === 'Y-m-d') {
            $format = 'yyyy-MM-dd';
        } elseif ($format === 'Y-m-d H:i:s') {
            $format = 'yyyy-MM-dd HH:mm:ss';
        }
        return $format;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class);
    }
}