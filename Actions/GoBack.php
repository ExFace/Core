<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iNavigate;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;

class GoBack extends AbstractAction implements iNavigate
{

    protected function init()
    {
        $this->setIconName(Icons::ARROW_LEFT);
    }

    protected function perform()
    {
        $this->setResultDataSheet($this->getInputDataSheet());
    }
}
?>