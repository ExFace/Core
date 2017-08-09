<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

class Adddays extends Formula
{

    function run($date, $days_to_add = null)
    {
        if (! $date)
            return;
        if (! $format)
            $format = $this->getWorkbench()->getConfig()->getOption('GLOBAL.DEFAULT_DATE_FORMAT');
        $date = new \DateTime($date);
        $interval = ($days_to_add < 0 ? 'N' : 'P') . intval($days_to_add) . 'D';
        $date->add(new \DateInterval($interval));
        return $date->format($format);
    }
}
?>