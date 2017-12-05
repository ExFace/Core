<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration sorting directions: ASC and DESC.
 * 
 * @method TextStylesDataType NORMAL(\exface\Core\CommonLogic\Workbench $workbench)
 * @method TextStylesDataType BOLD(\exface\Core\CommonLogic\Workbench $workbench)
 * @method TextStylesDataType ITALIC(\exface\Core\CommonLogic\Workbench $workbench)
 * @method TextStylesDataType STRIKETHROUGH(\exface\Core\CommonLogic\Workbench $workbench)
 * @method TextStylesDataType UNDERLINE(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class TextStylesDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const NORMAL = "NORMAL";
    const BOLD = "BOLD";
    const ITALIC = "ITALIC";
    const STRIKETHROUGH = "STRIKETHROUGH";
    const UNDERLINE = "UNDERLINE";
    
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
                $this->labels[$val] = $translator->translate('WIDGET.TEXT.STYLE_' . $val);
            }
        }
        
        return $this->labels;
    }

}
?>