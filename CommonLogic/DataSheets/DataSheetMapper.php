<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;

class DataSheetMapper implements iCanBeConvertedToUxon, ExfaceClassInterface {
    
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $fromMetaObject = null;
    
    private $toMetaObject = null;
    
    public function __construct(Workbench $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public function map(DataSheetInterface $source_sheet)
    {
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        // TODO
        return $uxon;
    }
    
    /**
     * 
     * @return Object
     */
    public function getFromMetaObject()
    {
        if (is_null($this->fromMetaObject)){
            // TODO add error code
            throw new DataSheetMapperError($this, 'No from-object defined in data sheet mapper!');
        }
        
        return $this->fromMetaObject;
    }

    /**
     * @param Object $object
     * @return DataSheetMapper
     */
    public function setFromMetaObject(Object $object)
    {
        $this->fromMetaObject = $object;
        return $this;
    }
    
    /**
     * 
     * @param string $alias_with_namespace
     * @return DataSheetMapper
     */
    public function setFromObjectAlias($alias_with_namespace)
    {
        return $this->setFromMetaObject($this->getWorkbench()->model()->getObject($alias_with_namespace));
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
   
    
}