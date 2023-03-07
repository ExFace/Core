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
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Tasks\CliTaskInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Selectors\PWASelector;
use exface\Core\DataTypes\ComparatorDataType;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class GeneratePWA extends AbstractActionDeferred implements iModifyData, iCanBeCalledFromCLI
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
    
    /**
     * 
     * @param TaskInterface $task
     * @throws ActionInputMissingError
     * @return PWAInterface
     */
    protected function getPWA(TaskInterface $task) : PWAInterface
    {
        switch (true) {
            case $task instanceof HttpTaskInterface:
                $inputData = $this->getInputDataSheet($task);
                $facadeClass = $inputData->getColumns()->get('PAGE_TEMPLATE__FACADE')->getValue(0);
                $pwaUid = $inputData->getColumns()->get('UID')->getValue(0);
                if (! $pwaUid || ! $facadeClass) {
                    throw new ActionInputMissingError($this, 'Cannot generate PWA: missing UID or facade selector!');
                }
                $facade = FacadeFactory::createFromString($facadeClass, $this->getWorkbench());
                break;
            case $task instanceof CliTaskInterface:
                $arg = $task->getCliArgument('selector');
                $selector = new PWASelector($this->getWorkbench(), $arg);
                $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PWA');
                $ds->getColumns()->addFromUidAttribute();
                $ds->getColumns()->addMultiple([
                    'PAGE_TEMPLATE__FACADE'
                ]);
                if ($selector->isUid()) {
                    $ds->getFilters()->addConditionFromString('UID', $selector->toString(), ComparatorDataType::EQUALS);
                } else {
                    $ds->getFilters()->addConditionFromString('ALIAS_WITH_NS', $selector->toString(), ComparatorDataType::EQUALS);
                }
                $ds->dataRead();
                $pwaUid = $ds->getUidColumn()->getValue(0);
                $facadeClass = $ds->getColumns()->get('PAGE_TEMPLATE__FACADE')->getValue(0);
                $facade = FacadeFactory::createFromString($facadeClass, $this->getWorkbench());
        }
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments(): array
    {
        return [
            (new ServiceParameter($this))
            ->setName('selector')
            ->setDescription('UID or namespaced alias of the PWA to regenerate.')
        ];
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
}