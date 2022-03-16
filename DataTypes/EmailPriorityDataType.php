<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Enumeration of email message priorities types: n1 (regular) and 1n (reverse).
 * 
 * @method EmailPriorityDataType HIGHEST(\exface\Core\CommonLogic\Workbench $workbench)
 * @method EmailPriorityDataType HIGH(\exface\Core\CommonLogic\Workbench $workbench)
 * @method EmailPriorityDataType NORMAL(\exface\Core\CommonLogic\Workbench $workbench)
 * @method EmailPriorityDataType LOW(\exface\Core\CommonLogic\Workbench $workbench)
 * @method EmailPriorityDataType LOWEST(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class EmailPriorityDataType extends NumberDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait {
        cast as castEnum;
    }
    
    const HIGHEST = 1;
    const HIGH = 2;
    const NORMAL = 3;
    const LOW = 4;
    const LOWEST = 5;
    
    public static function cast($value)
    {
        if (static::isValueEmpty($value)) {
            return null;
        }
        if (is_string($value)) {
            return static::getValueFromName($value);
        }
        return static::castEnum($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if (empty($this->labels)) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            
            foreach (EmailPriorityDataType::getValuesStatic() as $const => $val) {
                $this->labels[$val] = $translator->translate('EMAIL.PRIORITY.' . $const);
            }
        }
        
        return $this->labels;
    }
    
    /**
     * 
     * @param string|int $value
     * @return string|NULL
     */
    protected function getConstantName($value) : ?string
    {
        foreach (static::getValuesStatic() as $const => $val) {
            if ($value === $val || $value === $const) {
                return $const;
            }
        }
        return null;
    }
    
    /**
     * 
     * @param string $name
     * @return int|NULL
     */
    protected static function getValueFromName(string $name, bool $strict = true) : ?int
    {
        $name = mb_strtoupper($name);
        foreach (static::getValuesStatic() as $const => $val) {
            if ($name === $const) {
                return $val;
            }
        }
        if ($strict === true) {
            throw new DataTypeCastingError('Invalid email priority value "' . $name . '": expecting 1-5 or highest-lowest');
        }
        return null;
    }
}