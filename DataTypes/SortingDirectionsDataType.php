<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration - list of allowed values.
 * 
 * @method SortingDirectionsDataType ASC(\exface\Core\CommonLogic\Workbench $workbench)
 * @method SortingDirectionsDataType DESC(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class SortingDirectionsDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const ASC = "ASC";
    const DESC = "DESC";
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return [
            self::ASC => $translator->translate('GLOBAL.SORTING_DIRECTIONS.ASC'),
            self::DESC => $translator->translate('GLOBAL.SORTING_DIRECTIONS.DESC')
        ];
    }

}
?>