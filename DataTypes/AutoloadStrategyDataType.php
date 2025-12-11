<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration of autoload data options: never, always, if_visible, etc.
 *
 * @method AutoloadStrategyDataType never(\exface\Core\CommonLogic\Workbench $workbench)
 * @method AutoloadStrategyDataType always(\exface\Core\CommonLogic\Workbench $workbench)
 * @method AutoloadStrategyDataType if_visible(\exface\Core\CommonLogic\Workbench $workbench)
 *
 * @author Andrej Kabachnik
 *
 */
class AutoloadStrategyDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;

    const NEVER = "never";
    const ALWAYS = "always";
    const IF_VISIBLE = "if_visible";

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        $vals = $this::getValuesStatic();
        $labels = [];
        foreach ($vals as $val){
            $labels[$val] = str_replace('_', ' ', $val);
        }
        return $labels;
    }
}