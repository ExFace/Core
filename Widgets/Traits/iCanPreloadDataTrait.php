<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Widgets\DataPreloader;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;

trait iCanPreloadDataTrait {
    
    private $preloader = null;
    
    public function setPreloadData($uxonOrString): iCanPreloadData
    {
        if ($uxonOrString instanceof UxonObject) {
            $this->getPreloader()->importUxonObject($uxonOrString);
        } elseif (BooleanDataType::cast($uxonOrString) === true) {
            $this->getPreloader()->setPreloadAll();
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . gettype($uxonOrString) . '" received for property preload_data of widget ' . $this->getWidgetType() . ': expecting boolean or UXON!');
        }
        return $this;
    }
    
    public function isPreloadDataEnabled(): bool
    {
        return $this->getPreloader()->isEnabled();
    }
    
    public function prepareDataSheetToPreload(DataSheetInterface $dataSheet): DataSheetInterface
    {
        return $this->getPreloader()->prepareDataSheetToPreload($dataSheet);
    }
    
    public function getPreloader(): DataPreloader
    {
        if ($this->preloader === null) {
            $this->preloader = new DataPreloader($this);
        }
        return $this->preloader;
    }
}