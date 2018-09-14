<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSorterInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataSorter;
use exface\Core\Exceptions\UnexpectedValueException;

abstract class DataSorterFactory extends AbstractStaticFactory
{

    public static function createEmpty(Workbench $exface)
    {
        return new DataSorter($exface);
    }

    /**
     *
     * @param DataSheet $data_sheet            
     * @return DataSorterInterface
     */
    public static function createForDataSheet(DataSheetInterface $data_sheet)
    {
        $exface = $data_sheet->getWorkbench();
        $instance = new DataSorter($exface);
        $instance->setDataSheet($data_sheet);
        return $instance;
    }

    /**
     *
     * @param DataSheet $data_sheet            
     * @param UxonObject $uxon            
     * @return DataSorterInterface
     */
    public static function createFromUxon(DataSheetInterface $data_sheet, UxonObject $uxon)
    {
        $sorter = self::createForDataSheet($data_sheet);
        $sorter->importUxonObject($uxon);
        return $sorter;
    }

    /**
     *
     * @param DataSheet $data_sheet            
     * @param
     *            DataSorter | string | UxonObject $sorter_or_string_or_uxon
     * @throws UnexpectedValueException
     * @return DataSorterInterface
     */
    public function createFromAnything(DataSheetInterface $data_sheet, $sorter_or_string_or_uxon)
    {
        if ($sorter_or_string_or_uxon instanceof UxonObject) {
            $result = static::createFromUxon($this, $sorter_or_string_or_uxon);
        } elseif ($sorter_or_string_or_uxon instanceof DataSorter) {
            $result = $sorter_or_string_or_uxon;
        } else {
            throw new UnexpectedValueException('Cannot set aggregator "' . $sorter_or_string_or_uxon . '": only instantiated data aggregators or uxon objects allowed!');
        }
        return $result;
    }
}
?>