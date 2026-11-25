<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;

/**
 * Enumeration of model component keys from the installed component registry.
 *
 * Use this type for component selectors, including types supplied by other apps.
 * Registry keys are the allowed values, with component names as labels.
 * The inherited `values` property can restrict the enumeration for a particular
 * use case.
 *
 * ```json
 * {
 *     "alias": "\\exface\\Core\\DataTypes\\ModelComponentDataType"
 * }
 *
 * ```
 * 
 * @author Andrej Kabachnik
 */
class ModelComponentDataType extends StringEnumDataType
{
    /**
     * Initializes the enum from the registry before applying optional configuration.
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::__construct()
     *
     * @param DataTypeSelectorInterface $selector
     * @param mixed $value
     * @param UxonObject|null $configuration
     */
    public function __construct(DataTypeSelectorInterface $selector, $value = null, UxonObject $configuration = null)
    {
        $this->setShowValues(false);
        $this->setValues($selector->getWorkbench()->getComponentRegistry()->getComponentNames());
        parent::__construct($selector, $value, $configuration);
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getValueHints()
     */
    public function getValueHints(): array
    {
        $hints = [];
        $registry = $this->getWorkbench()->getComponentRegistry();
        foreach ($registry->getComponentKeys() as $key) {
            $hints[$key] = $registry->getComponentProperty($key, 'description');
        }
        return $hints;
    }
}