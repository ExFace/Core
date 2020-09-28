<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration built-in build recipies.
 *
 * @method BuildRecipeDataType COMPOSER_INSTALL(\exface\Core\CommonLogic\Workbench $workbench)
 * @method BuildRecipeDataType CLONE_LOCAL(\exface\Core\CommonLogic\Workbench $workbench)
 * // TODO add other @method
 *
 * @author Andrej Kabachnik
 *
 */
class TaskQueueTypeDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const TYPE_SYNC = "SYNC";
    const TYPE_ASYNC = "ASYNC";
    
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