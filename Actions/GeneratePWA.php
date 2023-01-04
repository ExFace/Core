<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\CommonLogic\AbstractActionDeferred;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\PWA\PWAInterface;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Exceptions\Actions\ActionInputMissingError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class GeneratePWA extends AbstractActionDeferred implements iModifyData
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon('cogs');
        $this->setInputObjectAlias('exface.Core.PWA');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performImmediately()
     */
    protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
    {
        $pwa = $this->getPWA($task, $transaction);
        return [$pwa];
    }
    
    protected function getPWA(TaskInterface $task) : PWAInterface
    {
        $inputData = $this->getInputDataSheet($task);
        $facadeClass = $inputData->getColumns()->get('PAGE_TEMPLATE__FACADE')->getValue(0);
        $pwaUid = $inputData->getColumns()->get('UID')->getValue(0);
        if (! $pwaUid || ! $facadeClass) {
            throw new ActionInputMissingError($this, 'Cannot generat PWA: missing UID or facade selector!');
        }
        $facade = FacadeFactory::createFromString($facadeClass, $this->getWorkbench());
        return $facade->getPWA($pwaUid);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    protected function performDeferred(PWAInterface $pwa = null, DataTransactionInterface $transaction = null) : \Generator
    {
        yield from $pwa->generateModel();
    }
}