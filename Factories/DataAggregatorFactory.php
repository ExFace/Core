<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataAggregatorInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregator;
use exface\Core\Exceptions\UnexpectedValueException;

abstract class DataAggregatorFactory extends AbstractFactory
{

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @return DataAggregatorInterface
     */
    public static function createForDataSheet(DataSheetInterface $data_sheet)
    {
        return new DataAggregator($data_sheet);
    }

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @param UxonObject $uxon            
     * @return DataAggregatorInterface
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
     * @return DataAggregatorInterface
     */
    public function createFromAnything(DataSheetInterface $data_sheet, $aggregator_or_string_or_uxon)
    {
        if ($aggregator_or_string_or_uxon instanceof UxonObject) {
            $result = static::createFromUxon($this, $aggregator_or_string_or_uxon);
        } elseif ($aggregator_or_string_or_uxon instanceof DataAggregator) {
            $result = $aggregator_or_string_or_uxon;
        } else {
            throw new UnexpectedValueException('Cannot set aggregator "' . $aggregator_or_string_or_uxon . '": only instantiated data aggregators or uxon objects allowed!');
        }
        return $result;
    }
}
?>