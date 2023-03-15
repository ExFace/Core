<?php
namespace exface\Core\Interfaces\PWA;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWAInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public function generateModel(DataTransactionInterface $transaction = null) : \Generator;
    
    public function getFacade(): FacadeInterface;
    
    public function getStartPage() : UiPageInterface;
    
    /**
     *
     * @return PWADatasetInterface[]
     */
    public function getDatasets() : array;
    
    /**
     *
     * @return PWARouteInterface[]
     */
    public function getRoutes() : array;
    
    /**
     *
     * @return UiMenuItemInterface[]
     */
    public function getMenuRoots() : array;
    
    /**
     *
     * @return ActionInterface[]
     */
    public function getActions() : array;
    
    public function getActionOfflineStrategy(ActionInterface $action) : string;
    
    public function loadModel(array $offlineStrategies = []) : PWAInterface;
    
    public function isModelLoaded() : bool;
    
    public function getBuildCache(string $filename, UserImpersonationInterface $userOrToken) : DataSheetInterface;
    
    public function setBuildCache(string $filename, string $content, string $mimetype, UserImpersonationInterface $userOrToken) : DataSheetInterface;
}