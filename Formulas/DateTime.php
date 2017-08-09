<?php
namespace exface\Core\Formulas;

class DateTime extends \exface\Core\CommonLogic\Model\Formula
{

    function run($date, $format = '')
    {
        $exface = $this->getWorkbench();
        if (! $date)
            return;
        if (! $format)
            $format = $exface->getCoreApp()->getTranslator()->translate('GLOBAL.DEFAULT_DATETIME_FORMAT');
        try {
            $date = new \DateTime($date);
        } catch (\Exception $e) {
            return $date;
        }
        return $date->format($format);
    }
}
?>