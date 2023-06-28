<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Joins any data to the to-sheet of the mapping using left or right JOINs similar to SQL.
 * 
 * **WARNING**: This mapper is beta only and not thoroughly tested!
 * 
 * This allows to append columns from one data sheet to another even if the objects
 * of the two sheets have no relations between each other. All you need to known,
 * is which columns on both sides should have the same values!
 * 
 * **NOTE:** This mapper does not change the meta object of the data - it just adds
 * columns. It is your responsibility to watch out for conflicts between attribute names
 * of the object of the input data and that of the `join_data_sheet`. Also keep in mind,
 * that the added column do not have correct data types.
 * 
 * For example, concider a data sheet with the number of deliveries per estimated time of
 * arrival (OTA) date. We can use a JOIN-mapping to include ALL dates now and not only
 * those that have deliveries:
 * 
 * ```
 *  {
 *     "joins": [
 *        {
 *          "join": "right",
 *          "join_input_data_on_attribute": "OTA",
 *          "join_data_sheet_on_attribute": "DATE",
 *          "join_data_sheet": {
 *            "object_alias": "exface.Core.DATE_DIMENSION",
 *            "filters": {
 *              "operator": "AND",
 *              "conditions": [
 *                {"expression": "START_DATE", "comparator": "==", "value": -30},
 *                {"expression": "END_DATE", "comparator": "==", "value": 0}
 *              ]
 *            },
 *            "columns": [
 *              {"attribute_alias": "DATE"}
 *            ]
 *         }
 *     }
 * }
 * 
 * ```
 * 
 * The result will have a row for every day and all columns of the mappers input sheet
 * with corresponding values if the match the day.
 * 
 * @IDEA automatically add filters over the join-on attribute to the joined data if the
 * input-data has filters over its join-on attribute.
 * 
 * @IDEA transfer data types from join-data column to the result (currently the resulting
 * columns are just strings)
 * 
 * @IDEA add the join-on attribute automatically to the from-data
 * 
 * @TODO is it really a good idea to join to the to-sheet? Can it be empty?
 * 
 * @author Andrej Kabachnik
 *
 */
class DataJoinMapping extends AbstractDataSheetMapping 
{
    const JOIN_TYPE_LEFT = 'left';
    
    const JOIN_TYPE_RIGHT = 'right';
    
    private $joinType = self::JOIN_TYPE_LEFT;
    
    private $joinSheet = null;
    
    private $inputSheetKey = null;
    
    private $inputSheetKeyExpression = null;
    
    private $joinSheetKey = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $joinSheet = $this->getJoinDataSheet();
        if (! $joinSheet->isFresh()) {
            $joinSheet->dataRead();
        }
        switch ($this->getJoinType()) {
            case self::JOIN_TYPE_LEFT:
                return $toSheet->joinLeft($joinSheet, $this->getJoinInputDataKeyColumn($fromSheet)->getName(), $this->getJoinDataKeyColumn($toSheet));
            case self::JOIN_TYPE_RIGHT:
                return $joinSheet->joinLeft($toSheet, $this->getJoinDataSheetOnAttributeAlias(), $this->getJoinInputDataOnAttributeAlias());
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function getJoinType() : string
    {
        return $this->joinType;
    }
    
    /**
     * The type of the JOIN: `left` or `right`.
     * 
     * @uxon-property type
     * @uxon-type [left,right]
     * @uxon-default left
     * @uxon-required true
     * 
     * @param string $type
     * @return DataJoinMapping
     */
    protected function setJoin(string $type) : DataJoinMapping
    {
        $this->joinType = mb_strtolower($type);
        return $this;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function getJoinDataSheet() : DataSheetInterface
    {
        return $this->joinSheet;
    }
    
    /**
     * The data sheet to join - can be based on any (even unrelated) object
     * 
     * @uxon-property join_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "columns": [{"attribute_alias": ""}], "filters": {"operator": "AND", "conditions": [{"expression": "", comparator: "==", "value": ""}]}}
     * @uxon-required true
     * 
     * @param UxonObject $value
     * @return DataJoinMapping
     */
    protected function setJoinDataSheet(UxonObject $value) : DataJoinMapping
    {
        $this->joinSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $value);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getJoinInputDataOnAttributeAlias() : string
    {
        return $this->inputSheetKey;
    }
    
    /**
     * 
     * @return ExpressionInterface
     */
    protected function getJoinInputDataOnExpression() : ExpressionInterface
    {
        if ($this->inputSheetKeyExpression === null) {
            $this->inputSheetKeyExpression = ExpressionFactory::createForObject($this->getMapper()->getFromMetaObject(), $this->inputSheetKey);
        }
        return $this->inputSheetKeyExpression;
    }
    
    /**
     * 
     * @param DataSheetInterface $fromSheet
     * @return DataColumnInterface
     */
    protected function getJoinInputDataKeyColumn(DataSheetInterface $fromSheet) : DataColumnInterface
    {
        return $fromSheet->getColumns()->getByExpression($this->getJoinInputDataOnExpression());
    }
    
    /**
     * Alias of the attribute of the input-data object that is to be used to join the mapped data
     * 
     * @uxon-property join_input_data_on_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return DataJoinMapping
     */
    protected function setJoinInputDataOnAttribute(string $value) : DataJoinMapping
    {
        $this->inputSheetKey = $value;
        $this->inputSheetKeyExpression = null;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getJoinDataSheetOnAttributeAlias() : string
    {
        return $this->joinSheetKey;
    }
    
    /**
     * 
     * @param DataSheetInterface $toSheet
     * @return DataColumnInterface
     */
    protected function getJoinDataKeyColumn(DataSheetInterface $toSheet) : DataColumnInterface
    {
        return $toSheet->getColumns()->getByExpression($this->getJoinDataSheetOnAttributeAlias());
    }
    
    /**
     * Alias of an attribute of the `data_sheet` object, that is to be used for the JOIN.
     * 
     * @uxon-property join_data_sheet_on_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return DataJoinMapping
     */
    protected function setJoinDataSheetOnAttribute(string $value) : DataJoinMapping
    {
        $this->joinSheetKey = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [$this->getJoinInputDataOnExpression()];
    }
}