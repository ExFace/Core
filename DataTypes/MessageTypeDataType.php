<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration for message types: error, info, warning, hint, success, etc.
 * 
 * @method MessageTypeDataType ERROR(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MessageTypeDataType WARNING(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MessageTypeDataType INFO(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MessageTypeDataType SUCCESS(\exface\Core\CommonLogic\Workbench $workbench)
 * @method MessageTypeDataType HINT(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class MessageTypeDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const ERROR = "ERROR";
    const WARNING = "WARNING";
    const INFO = "INFO";
    const SUCCESS = "SUCCESS";
    const HINT = "HINT";
    const QUESTION = "QUESTION";
    
    private $labels = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if (empty($this->labels)) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            
            foreach (static::getValuesStatic() as $val) {
                $this->labels[$val] = $translator->translate('MESSAGE.TYPES.' . $val);
            }
        }
        
        return $this->labels;
    }
}