<?php
namespace exface\Core\Formulas;

class Now extends \exface\Core\CommonLogic\Model\Formula
{

    function run($format = '')
    {
        $exface = $this->getWorkbench();
        if (! $format)
            $format = $exface->getConfig()->getOption('DEFAULT_DATETIME_FORMAT');
        $date = new \DateTime();
        return $date->format($format);
    }
}
?>