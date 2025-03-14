<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class JsonToRowsMapping extends AbstractDataSheetMapping 
{
    const JOIN_TYPE_LEFT = 'left';
    
    const JOIN_TYPE_RIGHT = 'right';
    
    private $joinType = self::JOIN_TYPE_LEFT;
    
    private $joinSheet = null;
    
    private $inputSheetKey = null;

    private $jsonColAlias = null;

    private $jsonColExpression = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $jsonCol = $this->getJsonColumn($fromSheet);

        $arrayOfJson = $jsonCol->getValues();
        $newRowsIdColName = $fromSheet->getUidColumn()->getName();
        foreach ($arrayOfJson as $rowIdx => $jsonString) {
            if ($jsonString === null) {
                continue;
            }
            $json = JsonDataType::decodeJson($jsonString);
            $newRows = array_merge($newRows, $this->flatten($json));
            foreach ($newRows as $i => $newRow) {
                $newRows[$i][$newRowsIdColName] = $fromSheet->getUidColumn()->getValue($rowIdx);
            }
        }

        $jsonSheet = DataSheetFactory::createFromObject($fromSheet->getMetaObject());
        $jsonSheet->addRows($newRows);
        $toSheet->joinLeft($jsonSheet, $toSheet->getUidColumn()->getName(), $newRowsIdColName);

        return $toSheet;
    }

    protected function flatten(array $json) : array
    {
        $rows = [];

        return $rows;
    }

    protected function getJsonColumn(DataSheetInterface $fromSheet) : DataColumnInterface
    {
        return $fromSheet->getColumns()->getByExpression($this->jsonColAlias);
    }

    /**
     * Attribute alias or expression to fetch the JSON from the input data
     * 
     * @uxon-property json_column
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return JsonToRowsMapping
     */
    protected function setJsonColumn(string $alias) : JsonToRowsMapping
    {
        $this->jsonColAlias = $alias;
        $this->jsonColExpression = ExpressionFactory::createForObject($this->getMapper()->getFromMetaObject(), $alias);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [$this->jsonColExpression];
    }
}