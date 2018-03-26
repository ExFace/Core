<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataColumnTotalInterface;
use exface\Core\CommonLogic\DataSheets\DataColumnTotal;
use exface\Core\Interfaces\Model\AggregatorInterface;

abstract class DataColumnTotalsFactory extends AbstractStaticFactory
{

    /**
     *
     * @param DataColumnInterface $data_column            
     * @return DataColumnTotalInterface
     */
    public static function createEmpty(DataColumnInterface $data_column)
    {
        $result = new DataColumnTotal($data_column);
        return $result;
    }

    /**
     *
     * @param DataColumnInterface $data_column            
     * @param AggregatorInterface|string $function_name            
     * @return DataColumnTotalInterface
     */
    public static function createFromString(DataColumnInterface $data_column, $aggregator_or_string)
    {
        $result = static::createEmpty($data_column);
        $result->setAggregator($aggregator_or_string);
        return $result;
    }

    /**
     *
     * @param DataColumnInterface $data_column            
     * @param UxonObject $uxon            
     * @return DataColumnTotalInterface
     */
    public static function createFromUxon(DataColumnInterface $data_column, UxonObject $uxon)
    {
        $result = static::createEmpty($data_column);
        $result->importUxonObject($uxon);
        return $result;
    }
}
?>