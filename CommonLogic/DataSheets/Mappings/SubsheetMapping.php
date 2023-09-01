<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Applies a data mapper to a column with subsheets - i.e. to each subsheet in that column.
 * 
 * ## Examples
 * 
 * TODO
 * 
 * @author Andrej Kabachnik
 *
 */
class SubsheetMapping extends AbstractDataSheetMapping 
{
    private $fromSubsheetRelationPathString = null;
    
    private $toSubsheetRelationPathString = null;
    
    private $subsheetMapperUxon = null;
    
    private $subsheetMapper = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $subsheetMapper = $this->getSubsheetMapper();
        $fromSubsheetCol = $fromSheet->getColumns()->getByExpression($this->getFromSubsheetRelationString());
        if (! $fromSubsheetCol) {
            throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Subsheet-column "' . $this->getFromSubsheetRelationString() . '" not found in data!');
        }
        
        // Make sure, the to-sheet has a column for the subsheet
        if (! $toSubsheetCol = $toSheet->getColumns()->getByExpression($this->getToSubsheetRelationString())) {
            $toSubsheetCol = $toSheet->getColumns()->addFromExpression($this->getToSubsheetRelationString());
        }
        
        if ($logbook !== null) {
            $logbook->addLine("Subsheet `{$fromSubsheetCol->getName()}` -> `{$toSubsheetCol->getName()}`");
            $logbook->addIndent(1);
        }
        
        foreach ($fromSubsheetCol->getValues() as $i => $subsheetVal) {
            if ($subsheetVal === null || $subsheetVal === '') {
                continue;
            }
            if (! is_array($subsheetVal) && ! $subsheetVal instanceof UxonObject) {
                throw new \UnexpectedValueException('Invalid subsheet format');
            }
            $subsheet = DataSheetFactory::createFromUxon($this->getWorkbench(), UxonObject::fromAnything($subsheetVal));
            $readMissingData = null;
            // If the subsheet is completely empty, make sure no to attempt to read it. Otherwise
            // column mappers would add columns and the mapper would attempt to read the entire
            // data not filtered at all. Subsheets do not have a filter over their parent most of the
            // time - that filter is added automatically, when writing is performed.
            if ($subsheet->isEmpty()) {
                $readMissingData = false;
            }
            $toSubsheet = $subsheetMapper->map($subsheet, $readMissingData, $logbook);
            $toSubsheetCol->setValue($i, $toSubsheet->exportUxonObject());
        }  
        
        if ($fromSheet->getMetaObject() === $toSheet->getMetaObject()) {
            if ($toSubsheetCol !== $removeCol = $toSheet->getColumns()->getByExpression($this->getFromSubsheetRelationString())) {
                $toSheet->getColumns()->remove($removeCol);
            }
        }
        
        if ($logbook !== null) $logbook->addIndent(-1);
        
        return $toSheet;
    }
    
    /**
     * 
     * @return string
     */
    protected function getFromSubsheetRelationString() : string
    {
        return $this->fromSubsheetRelationPathString;
    }
    
    /**
     * Relation path from the to-object to the subsheet object
     * 
     * @uxon-property from_subsheet_relation_path
     * @uxon-type metamodel:relation
     * @uxon-required true
     * 
     * @param string $value
     * @return SubsheetMapping
     */
    protected function setFromSubsheetRelationPath(string $value) : SubsheetMapping
    {
        $this->fromSubsheetRelationPathString = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    protected function getFromSubsheetObject() : MetaObjectInterface
    {
        return RelationPathFactory::createFromString($this->getMapper()->getFromMetaObject(), $this->getFromSubsheetRelationString())->getEndObject();
    }
    
    /**
     *
     * @return string
     */
    protected function getToSubsheetRelationString() : string
    {
        return $this->toSubsheetRelationPathString;
    }
    
    /**
     * Relation path from the to-object of the parent mapper to the object of the resulting subsheet
     *
     * @uxon-property to_subsheet_relation_path
     * @uxon-type metamodel:relation
     * @uxon-required true
     *
     * @param string $value
     * @return SubsheetMapping
     */
    protected function setToSubsheetRelationPath(string $value) : SubsheetMapping
    {
        $this->toSubsheetRelationPathString = $value;
        return $this;
    }
    
    /**
     *
     * @return MetaObjectInterface
     */
    protected function getToSubsheetObject() : MetaObjectInterface
    {
        return RelationPathFactory::createFromString($this->getMapper()->getToMetaObject(), $this->getToSubsheetRelationString())->getEndObject();
    }
    
    /**
     * 
     * @return DataSheetMapperInterface
     */
    protected function getSubsheetMapper() : DataSheetMapperInterface
    {
        if ($this->subsheetMapper === null) {
            if ($this->subsheetMapperUxon === null) {
                throw new DataMappingConfigurationError($this, 'Missing subsheet_mapper in to-subsheet-mapping configuration!');
            }
            $this->subsheetMapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $this->subsheetMapperUxon, $this->getFromSubsheetObject(), $this->getToSubsheetObject());
        }
        return $this->subsheetMapper;
    }
    
    /**
     * Mapper to map the from-sheet to each subsheet inside the to-sheet
     * 
     * @uxon-property subsheet_mapper
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     * @uxon-template {"column_to_column_mappings": [{"from": "", "to": ""}]}
     * @uxon-required true
     * 
     * @param UxonObject $value
     * @return SubsheetMapping
     */
    protected function setSubsheetMapper(UxonObject $value) : SubsheetMapping
    {
        $this->subsheetMapperUxon = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        // IDEA lazy-load the subsheets here?
        // Columns required for the subsheet-mapper will be loaded by that mapper, not here
        return [];
    }
}