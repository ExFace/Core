<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration for widget visibilities: normal, promoted, hidden and optional.
 * 
 * @method WidgetVisibilityDataType NORMAL(\exface\Core\CommonLogic\Workbench $workbench)
 * @method WidgetVisibilityDataType PROMOTED(\exface\Core\CommonLogic\Workbench $workbench)
 * @method WidgetVisibilityDataType OPTIONAL(\exface\Core\CommonLogic\Workbench $workbench)
 * @method WidgetVisibilityDataType HIDDEN(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetVisibilityDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const NORMAL = "NORMAL";
    const PROMOTED = "PROMOTED";
    const OPTIONAL = "OPTIONAL";
    const HIDDEN = "HIDDEN";
    
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
                $this->labels[$val] = $translator->translate('WIDGET.VISIBILITY.' . $val);
            }
        }
        
        return $this->labels;
    }

}
?>