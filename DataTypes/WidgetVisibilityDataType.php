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
    
    const NORMAL = 50;
    const PROMOTED = 90;
    const OPTIONAL = 30;
    const HIDDEN = 10;
    
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
                $this->labels[$val] = $translator->translate('WIDGET.VISIBILITY.' . $this->getConstantName($val));
            }
        }
        
        return $this->labels;
    }

    protected function getConstantName(int $value) : ?string
    {
        switch ($value) {
            case 10: return 'HIDDEN';
            case 30: return 'OPTIONAL';
            case 50: return 'NORMAL';
            case 90: return 'PROMOTED';
        }
        return null;
    }
}
?>