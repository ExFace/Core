<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Actions\iResetWidgets;

/**
 * This action resets it's input widget - e.g. a DataTable, a Form, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResetWidget extends ReadData implements iResetWidgets
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    public function init()
    {
        parent::init();
        $this->setIcon(Icons::ERASER);
    }
}
?>