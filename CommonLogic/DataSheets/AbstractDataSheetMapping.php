<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataMappingInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Uxon\DataSheetMapperSchema;

/**
 * Base for built-in data mappers with a common constructor and other basic stuff.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractDataSheetMapping implements DataMappingInterface 
{
    use ImportUxonObjectTrait;
    
    private $mapper = null;
    
    private $uxon = null;
    
    /**
     * 
     * @param DataSheetMapper $mapper
     */
    public function __construct(DataSheetMapper $mapper, UxonObject $uxon = null)
    {
        $this->mapper = $mapper;
        $this->uxon = $uxon;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->uxon ?? new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getMapper()
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->mapper->getWorkbench();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return DataSheetMapperSchema::class;
    }
}