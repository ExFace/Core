<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of queded tasks status
 * 
 * @author Ralf Mulansky
 *
 */
class QueuedTaskStateDataType extends IntegerDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    CONST STATUS_QUEUED = "10";
    CONST STATUS_INPROGRESS = "50";
    CONST STATUS_ERROR = "70";
    CONST STATUS_RESOLVED = "90";
    CONST STATUS_DONE = "99";
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if (empty($this->labels)) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            
            foreach (static::getValuesStatic() as $const => $val) {
                $this->labels[$val] = $translator->translate('TASK.QUEUE.' . $const);
            }
        }
        
        return $this->labels;
    }

}
?>