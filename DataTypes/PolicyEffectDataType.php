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
class PolicyEffectDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const PERMIT = "P";
    const DENY = "D";
    
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