<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Exceptions\Actions\ActionRuntimeError;

/**
 * Clears the entire cache of the workbench.
 * 
 * This action does not support any parameters or input data.
 * 
 * @author Andrej Kabachnik
 *
 */
class ClearCache extends AbstractAction implements iCanBeCalledFromCLI
{
    private $clearOpCache = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getIcon()
     */
    public function getIcon()
    {
        return parent::getIcon() ?? Icons::RECYCLE;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $this->getWorkbench()->getCache()->clear();
        $resultText = $this->translate('RESULT');
        if ($this->getClearOpcache()) {
            if (! opcache_reset()) {
                throw new ActionRuntimeError($this, 'Could not clear OPcache!');
            }
            $resultText .= ' ' . $this->translate('RESULT_OPCACHE');
        }
        return ResultFactory::createMessageResult($task, $this->getResultMessageText() ?? $resultText);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments(): array
    {
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions(): array
    {
        return [];
    }
    
    /**
     * 
     * @return bool
     */
    protected function getClearOpcache() : bool
    {
        return $this->clearOpCache;
    }
    
    /**
     * Set to TRUE to clear PHPs OPCache too
     * 
     * @uxon-property clear_opcache
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return ClearCache
     */
    public function setClearOpcache(bool $value) : ClearCache
    {
        $this->clearOpCache = $value;
        return $this;
    }
}