<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataAggregationInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Exceptions\UnexpectedValueException;

abstract class DataAggregationFactory extends AbstractFactory
{

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @return DataAggregationInterface
     */
    public static function createForDataSheet(DataSheetInterface $data_sheet)
    {
        return new DataAggregation($data_sheet);
    }

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @param UxonObject $uxon            
     * @return DataAggregationInterface
     */
    public static function createFromUxon(DataSheetInterface $data_sheet, UxonObject $uxon)
    {
        $result = self::createForDataSheet($data_sheet);
        $result->importUxonObject($uxon);
        return $result;
    }

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @param unknown $aggregator_or_string_or_uxon            
     * @throws UnexpectedValueException
     * @return DataAggregationInterface
     */
    public function createFromAnything(DataSheetInterface $data_sheet, $aggregator_or_string_or_uxon)
    {
        if ($aggregator_or_string_or_uxon instanceof UxonObject) {
            $result = static::createFromUxon($this, $aggregator_or_string_or_uxon);
        } elseif ($aggregator_or_string_or_uxon instanceof DataAggregation) {
            $result = $aggregator_or_string_or_uxon;
        } else {
            throw new UnexpectedValueException('Cannot set aggregator "' . $aggregator_or_string_or_uxon . '": only instantiated data aggregators or uxon objects allowed!');
        }
        return $result;
    }
}
?>