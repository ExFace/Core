<?php
namespace exface\Core\Formulas;

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
            $format = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('GLOBAL.DEFAULT_DATE_FORMAT');
        try {
            $date = new \DateTime($date);
        } catch (\Exception $e) {
            return $date;
        }
        return $date->format($format);
    }
}
?>