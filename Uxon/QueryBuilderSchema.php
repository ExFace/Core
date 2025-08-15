<?php
namespace exface\Core\Uxon;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\UxonSchemaDataType;
use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\Exceptions\RuntimeException;
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
        return UxonSchemaDataType::QUERYBUILDER;
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
    public function getProperties(string $prototypeClass, UxonObject $uxon, array $path) : array
    {
        if ($this->getLevel() !== null) {
            $arr = [];
            $level = strtolower($this->getLevel());
            foreach ($this->getPropertiesSheet($prototypeClass)->getRows() as $row) {
                $target = strtolower($row['TARGET'] ?? '');
                if (! $target || $target === $level) {
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
     * @inheritdoc 
     * @see UxonSchema::loadPropertiesSheet()
     */
    protected function loadPropertiesSheet(string $prototypeClass, string $aliasOfAnnotationObject = 'exface.Core.UXON_PROPERTY_ANNOTATION') : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_QUERY_BUILDER_ANNOTATION');
        $ds->getColumns()->addMultiple([
            'PROPERTY',
            'TYPE',
            'TEMPLATE',
            'DEFAULT',
            'REQUIRED',
            'TRANSLATABLE',
            'TARGET' // Additional attribute for query builder annotations!!!
        ]);
        $filepathRelative = $this->getFilenameForEntity($prototypeClass);
        $ds->getFilters()->addConditionFromString('FILE', $filepathRelative);
        try {
            $ds->dataRead();
        } catch (\Throwable $e) {
            throw new RuntimeException('Cannot read UXON properties from file "' . $filepathRelative . '". ' . $e->getMessage(), null, $e);
        }
        return $ds;
    }
}