<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\RowArrayDataType;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\DataTypes\ArrayDataType;

/**
 * Puts values of selected column in a subsheet, mapping a large flat data sheet ot a smaller one with subsheets in each row
 * 
 * ## Examples
 * 
 * ### Multiple from-rows -> single to-row with subsheet
 * 
 * The following mapper will transform a sheet with UI pages to a sheet with a single page
 * group containing a subsheet with theese pages as a subsheet in its column `PAGE_GROUP_PAGES`.
 * If the result is saved, it would create new page group containing all pages from the
 * initial data sheet.
 * 
 * ```
 *  {
 *      "from_object_alias": "exface.Core.PAGE",
 *      "to_object_alias": "exface.Core.PAGE_GROUP",
 *      "column_to_column_mappings": [
 *          {"from": "='Unnamed page group'", "to": "NAME"}
 *      ],
 *      "to_subsheet_mappings": [
 *          {
 *              "subsheet_relation_path": "PAGE_GROUP_PAGES",
 *              "subsheet_mapper": {
 *                  "column_to_column_mappings": [
 *                      {"from": "UID", "to": "PAGE"}
 *                  ]
 *              }
 *          }
 *      ]
 *  }
 * 
 * ```
 * 
 * ### Multiple from-rows -> multipl to-rows with subsheets in each
 * 
 * We can also create multiple subsheets at once - one for every set of key values in the from-sheet. 
 * This example will create a separate page group for every app among the selected pages and put this
 * page group into the app. 
 * 
 * **NOTE:** the keys in the two sheets may be composed of multiple columns, these need to be listed
 * in the same order in both properties: `from_sheet_key_column` and `to_sheet_key_columns`. The number
 * of columns also MUST match.
 * 
 * ```
 *  {
 *      "from_object_alias": "exface.Core.PAGE",
 *      "to_object_alias": "exface.Core.PAGE_GROUP",
 *      "column_to_column_mappings": [
 *          {"from": "='Unnamed page group'", "to": "NAME"},
 *          {"from": "APP", "to": "APP"},
 *      ],
 *      "from_sheet_key_columns": [
 *          "APP"
 *      ],
 *      "to_sheet_key_columns": [
 *          "APP"
 *      ],
 *      "subsheet_mapper": [
 *          {
 *              "subsheet_relation_path": "PAGE_GROUP_PAGES",
 *              "subsheet_mapper": {
 *                  "column_to_column_mappings": [
 *                      {"from": "UID", "to": "PAGE"}
 *                  ]
 *              }
 *          }
 *      ]
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class DataToSubsheetMapping extends AbstractDataSheetMapping 
{
    private $relationFromToObjToSubsheet = null;
    
    private $subsheetMapperUxon = null;
    
    private $subsheetMapper = null;
    
    private $fromKeyColumns = [];
    
    private $toKeyColumns = [];
    
    private $toAggregatedMappingsUxon = null;
    
    private $toAggregatedMappings = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $toRows = $toSheet->getRows();
        $toRowsUnique = RowArrayDataType::findUniqueRows($toRows);
        
        $subsheetMapper = $this->getSubsheetMapper();
        
        // Make sure, the to-sheet has a column for the subsheet
        if (! $subsheetCol = $toSheet->getColumns()->getByExpression($this->getSubsheetRelationString())) {
            $subsheetCol = $toSheet->getColumns()->addFromExpression($this->getSubsheetRelationString());
        }
        
        $fromKeyCols = $this->getFromSheetKeyColumnNames();
        $toKeyCols = $this->getToSheetKeyColumnNames(); 
        if (count($fromKeyCols) !== count($toKeyCols)) {
            throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Invalid configuration for subsheet-mapping: `from_sheet_key_columns` and `to_sheet_key_columns` have different number of entries!');
        }
        
        // If we don't need to split by keys, make a single to-row with a subsheet from all from-rows
        // Otherwise use the keys to split the from-rows into multiple to-rows and thus multiple subsheets
        if (empty($fromKeyCols)) {
            if (count($toRowsUnique) > 1) {
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map data to a single subsheet: multiple unique to-rows require `from_sheet_key_columns` to be defined!');
            }
            $subsheet = $subsheetMapper->map($fromSheet);
            $toRowsUnique[0][$subsheetCol->getName()] = $subsheet->exportUxonObject();
            $toSheet->removeRows()->addRows($toRowsUnique);
        } else {
            // Collect from-rows for every from-key
            $fromRowsByKey = [];
            foreach ($fromSheet->getRows() as $fromRow) {
                $fromKey = [];
                $toKey = [];
                foreach ($fromKeyCols as $keyPartIdx => $fromKeyColName) {
                    $keyPart = $fromRow[$fromKeyColName];
                    $fromKey[$fromKeyColName] = $keyPart;
                    $toKey[$toKeyCols[$keyPartIdx]] = $keyPart;
                }
                $fromKeyStr = json_encode($fromKey);
                $fromRowsByKey[$fromKeyStr]['rows'][] = $fromRow;
                $fromRowsByKey[$fromKeyStr]['from_key'] = $fromKey;
                $fromRowsByKey[$fromKeyStr]['to_key'] = $toKey;
            }
            
            
            // Calculate the new rows for the to-sheet
            foreach ($fromRowsByKey as $arr) {
                $toKey = $arr['to_key'];
                $fromKey = $arr['from_key'];
                $fromRows = $arr['rows'];
                
                $filter = RowArrayDataType::filter();
                foreach ($toKey as $toKeyColName => $keyPart) {
                    $filter->addAnd($toKeyColName, $keyPart, ComparatorDataType::EQUALS);
                }
                foreach (array_keys($filter->filter($toRowsUnique, true)) as $toRowIdx) {
                    $subsheet = $subsheetMapper->map($fromSheet->copy()->removeRows()->addRows($fromRows));
                    $toRowsUnique[$toRowIdx][$subsheetCol->getName()] = $subsheet->exportUxonObject();
                    
                    foreach ($this->getToAggregationsUxon() as $uxon) {
                        $aggrTo = $uxon->getProperty('to');
                        if (! $aggrToCol = $toSheet->getColumns()->getByExpression($aggrTo)) {
                            $aggrToCol = $toSheet->getColumns()->addFromExpression($aggrTo);
                        }
                        $aggrFrom = $uxon->getProperty('from');
                        $aggrFromAttrAlias = DataAggregation::stripAggregator($aggrFrom);
                        $aggregator = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $aggrFrom);
                        $aggrFromCol = $fromSheet->getColumns()->getByExpression($aggrFromAttrAlias);
                        if (! $aggrFromCol) {
                            throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot find data to aggregate in from-sheet: "' . $aggrFrom . '"!');
                        }
                        $aggrFromVals = array_column($fromRows, $aggrFromCol->getName());
                        $toRowsUnique[$toRowIdx][$aggrTo] = $aggrToCol->getDataType()->parse(ArrayDataType::aggregateValues($aggrFromVals, $aggregator));
                    }
                }
            }
            $toSheet->removeRows()->addRows($toRowsUnique);
        }
        
        return $toSheet;
    }
    
    /**
     * 
     * @return string
     */
    protected function getSubsheetRelationString() : string
    {
        return $this->relationFromToObjToSubsheet;
    }
    
    /**
     * Relation path from the to-object to the subsheet object
     * 
     * @uxon-property subsheet_relation_path
     * @uxon-type metamodel:relation
     * @uxon-required true
     * 
     * @param string $value
     * @return DataToSubsheetMapping
     */
    protected function setSubsheetRelationPath(string $value) : DataToSubsheetMapping
    {
        $this->relationFromToObjToSubsheet = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    protected function getSubsheetObject() : MetaObjectInterface
    {
        return RelationPathFactory::createFromString($this->getMapper()->getToMetaObject(), $this->getSubsheetRelationString())->getEndObject();
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
            $this->subsheetMapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $this->subsheetMapperUxon, $this->getMapper()->getFromMetaObject(), $this->getSubsheetObject());
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
     * @return DataToSubsheetMapping
     */
    protected function setSubsheetMapper(UxonObject $value) : DataToSubsheetMapping
    {
        $this->subsheetMapperUxon = $value;
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getFromSheetKeyColumnNames() : array
    {
        return $this->fromKeyColumns;
    }
    
    /**
     * Put data of the rows of from-sheet into a single subsheet where the values of these columns are equal 
     * 
     * @uxon-property from_sheet_key_columns
     * @uxon-type metamodel:attribute[]|array
     * @uxon-template [""]
     * @uxon-required true
     * 
     * @return array
     */
    protected function setFromSheetKeyColumns(UxonObject $value) : DataToSubsheetMapping
    {
        $this->fromKeyColumns = $value->toArray();
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getToSheetKeyColumnNames() : array
    {
        return $this->toKeyColumns;
    }
    
    /**
     * Put the subsheet into the row of the to-sheet where these columns match the `from_key_columns` in the from-sheet.
     *
     * @uxon-property to_sheet_key_columns
     * @uxon-type metamodel:attribute[]|array
     * @uxon-template [""]
     * @uxon-required true
     *
     * @return array
     */
    protected function setToSheetKeyColumns(UxonObject $value) : DataToSubsheetMapping
    {
        $this->toKeyColumns = $value->toArray();
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        $exprs = [];
        foreach ($this->getSubsheetMapper()->getMappings() as $map) {
            $exprs = array_merge($exprs, $map->getRequiredExpressions($dataSheet));
        }
        return $exprs;
    }
    
    protected function getToAggregationsUxon() : UxonObject
    {
        return $this->toAggregatedMappings;
    }
    
    /**
     * Sum up values of these from-columns instead of putting them into a subsheet
     * 
     * @uxon-property to_aggregations
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataColumnMapping[]
     * @uxon-template [{"from": "", "to": ""}]
     * 
     * @param UxonObject $arrayOfColToColMappings
     * @return DataToSubsheetMapping
     */
    protected function setToAggregations(UxonObject $arrayOfColToColMappings) : DataToSubsheetMapping
    {
        $this->toAggregatedMappings = $arrayOfColToColMappings;
        return $this;
    }
}