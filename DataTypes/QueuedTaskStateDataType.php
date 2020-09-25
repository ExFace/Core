<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of security policy combining algorithms: deny-overrides, etc.
 * 
 * @method PolicyEffectDataType DENY(\exface\Core\CommonLogic\Workbench $workbench)
 * @method PolicyEffectDataType PERMIT(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class QueuedTaskStateDataType extends IntegerDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    CONST QUEUE_STATUS_QUEUED = 10;
    CONST QUEUE_STATUS_INPROGRESS = 50;
    CONST QUEUE_STATUS_ERROR = 70;
    CONST QUEUE_STATUS_DONE = 99;
    
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
                $this->labels[$val] = $translator->translate('SECURITY.POLICIES.EFFECT.' . $const);
            }
        }
        
        return $this->labels;
    }

}
?>