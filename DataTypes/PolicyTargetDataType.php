<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of security policy target types: user roles, page groups, actions, etc.
 * 
 * @method PolicyTargetDataType USER_ROLE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method PolicyTargetDataType PAGE_GROUP(\exface\Core\CommonLogic\Workbench $workbench)
 * @method PolicyTargetDataType ACTION(\exface\Core\CommonLogic\Workbench $workbench)
 * @method PolicyTargetDataType OBJECT(\exface\Core\CommonLogic\Workbench $workbench)
 * @method PolicyTargetDataType FACADE(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class PolicyTargetDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const USER_ROLE = 'USER_ROLE';
    const PAGE_GROUP = 'PAGE_GROUP';
    const ACTION = 'ACTION';
    const META_OBJECT = 'OBJECT';
    const FACADE = 'FACADE';
    
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
                $this->labels[$val] = $translator->translate('SECURITY.POLICIES.TARGET.' . $const);
            }
        }
        
        return $this->labels;
    }
}