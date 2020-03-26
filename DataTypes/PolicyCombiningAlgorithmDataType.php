<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of security policy combining algorithms: deny-unless-permit, etc.
 * 
 * ## Deny unless permit
 * 
 * The “Deny-unless-permit” combining algorithm is intended for those cases where a permit decision should have 
 * priority over a deny decision, and an “Indeterminate” or “NotApplicable” must never be the result. It is 
 * particularly useful at the top level in a policy structure to ensure that a PDP will always return a definite 
 * “Permit” or “Deny” result. This algorithm has the following behavior.
 * 
 * 1. If any decision is "Permit", the result is "Permit".
 * 2. Otherwise, the result is "Deny".
 * 
 * ## Permit unless deny
 * 
 * The “Permit-unless-deny” combining algorithm is intended for those cases where a deny decision should have priority 
 * over a permit decision, and an “Indeterminate” or “NotApplicable” must never be the result. It is particularly useful 
 * at the top level in a policy structure to ensure that a PDP will always return a definite “Permit” or “Deny” result. 
 * This algorithm has the following behavior.
 * 
 * 1. If any decision is "Deny", the result is "Deny".
 * 2. Otherwise, the result is "Permit".
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
    
    //const DENY_OVERRIDES = "deny-overrides";
    //const PERMIT_OVERRIDES = "permit-overrides";
    const DENY_UNLESS_PERMIT = "deny-unless-permit";
    const PERMIT_UNLESS_DENY = "permit-unless-deny";
    
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