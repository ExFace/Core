<?php
namespace exface\Core\CommonLogic\PWA;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\PWA\PWAInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\PWA\PWADatasetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Actions\ActionInterface;

class PWADataset implements PWADatasetInterface
{
    use ImportUxonObjectTrait;
    
    private $pwa = null;
    
    private $metaObject = null;
    
    private $dataSheet = null;
    
    private $actions =  [];
    
    public function __construct(PWAInterface $pwa, MetaObjectInterface $object)
    {
        $this->pwa = $pwa;
        $this->metaObject = $object;
    }
    
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getPWA()
     */
    public function getPWA(): PWAInterface
    {
        return $this->pwa;
    }
    
    public function getDataSheet(): DataSheetInterface
    {
        if ($this->dataSheet === null) {
            $this->dataSheet = DataSheetFactory::createFromObject($this->getMetaObject());
        }
        return $this->dataSheet;
    }

    public function getMetaObject(): MetaObjectInterface
    {
        return $this->metaObject;
    }
    
    public function addAction(ActionInterface $action) : PWADatasetInterface
    {
        $this->actions[] = $action;
        return $this;
    }
    
    /**
     * 
     * @return ActionInterface[]
     */
    public function getActions() : array
    {
        return $this->actions;
    }
}