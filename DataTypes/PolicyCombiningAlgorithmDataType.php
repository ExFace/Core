<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of security policy combining algorithms: deny-overrides, etc.
 * 
 * @method PolicyCombiningAlgorithmDataType DENY_OVERRIDES(\exface\Core\CommonLogic\Workbench $workbench)
 * @method PolicyCombiningAlgorithmDataType PERMIT_OVERRIDES(\exface\Core\CommonLogic\Workbench $workbench)
 * @method PolicyCombiningAlgorithmDataType DENY_UNLESS_PERMIT(\exface\Core\CommonLogic\Workbench $workbench)
 * @method PolicyCombiningAlgorithmDataType PERMIT_UNLESS_DENY(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class PolicyCombiningAlgorithmDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const DENY_OVERRIDES = "deny-overrides";
    const PERMIT_OVERRIDES = "permit-overrides";
    const DENY_UNLESS_PERMIT = "deny-unless-permit";
    const PERMIT_UNLESS_DENY = "permit-unles-deny";
    
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
                $this->labels[$val] = $translator->translate('SECURITY.POLICIES.COMBINING.' . $const);
            }
        }
        
        return $this->labels;
    }

}
?>