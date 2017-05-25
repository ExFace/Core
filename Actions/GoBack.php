<?php

namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iNavigate;
use exface\Core\CommonLogic\AbstractAction;

class GoBack extends AbstractAction implements iNavigate
{

    protected function init()
    {
        $this->setIconName('back');
    }

    protected function perform()
    {
        $this->setResultDataSheet($this->getInputDataSheet());
    }
}
?>