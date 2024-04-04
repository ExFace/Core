<?php
namespace exface\Core\Uxon;

use exface\Core\DataTypes\UxonSchemaNameDataType;
use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * UXON-schema class for facade configuration.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class QueryBuilderSchema extends UxonSchema
{
    const LEVEL_OBJECT = 'Object';
    const LEVEL_ATTRIBUTE = 'Attribute';
    
    private $level = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonSchema $parentSchema
     * @param string $level
     */
    public function __construct(WorkbenchInterface $workbench, UxonSchema $parentSchema = null, string $level = null)
    {
        parent::__construct($workbench, $parentSchema);
        $this->level = $level;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getSchemaName()
     */
    public static function getSchemaName() : string
    {
        return UxonSchemaNameDataType::QUERYBUILDER;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getDefaultPrototypeClass()
     */
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractQueryBuilder::class;
    }
    
    public function getLevel() : ?string
    {
        return $this->level;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Uxon\UxonSchema::getProperties()
     */
    public function getProperties(string $prototypeClass) : array
    {
        if ($this->getLevel() !== null) {
            $arr = [];
            foreach ($this->getPropertiesSheet($prototypeClass)->getRows() as $row) {
                if (strtolower($row['TARGET']) === strtolower($this->getLevel())) {
                    $arr[] = $row['PROPERTY'];
                }
            }
            return $arr;
        }
        
        if ($col = $this->getPropertiesSheet($prototypeClass)->getColumns()->get('PROPERTY')) {
            return $col->getValues(false);
        }
        
        return [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPropertiesSheet()
     */
    protected function getPropertiesSheet(string $prototypeClass) : DataSheetInterface
    {
        if ($cache = $this->prototypePropCache[$prototypeClass]) {
            return $cache;
        }
        
        if ($cache = $this->getCache($prototypeClass, 'properties')) {
            return DataSheetFactory::createFromUxon($this->getWorkbench(), $cache);
        }
        
        $filepathRelative = $this->getFilenameForEntity($prototypeClass);
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_QUERY_BUILDER_ANNOTATION');
        $ds->getColumns()->addMultiple([
            'PROPERTY',
            'TYPE',
            'TEMPLATE',
            'DEFAULT',
            'REQUIRED',
            'TRANSLATABLE',
            'TARGET'
        ]);
        $ds->getFilters()->addConditionFromString('FILE', $filepathRelative);
        try {
            $ds->dataRead();
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            // TODO
        }
        $this->prototypePropCache[$prototypeClass] = $ds;
        $this->setCache($prototypeClass, 'properties', $ds->exportUxonObject());
        
        return $ds;
    }
}