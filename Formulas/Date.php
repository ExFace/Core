<?php
namespace exface\Core\Formulas;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateDataType;

class Date extends \exface\Core\CommonLogic\Model\Formula
{

    function run($date, $format = '')
    {
        if (! $date)
            return;
        return $this->formatDate($date, $format);
    }

    function formatDate($date, $format = '')
    {
        if (! $format)
            $format = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT');
        try {
            $date = new \DateTime($date);
        } catch (\Exception $e) {
            return $date;
        }
        return $date->format($format);
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