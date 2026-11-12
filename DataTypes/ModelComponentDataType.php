<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;

/**
 * Enumeration of model component keys from the installed component registry.
 *
 * Use this type for component selectors, including types supplied by other apps.
 * Keys are used as both values and labels. The inherited `values` property can
 * restrict the enumeration for a particular use case.
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
        parent::__construct($selector, $value, $configuration);
    }
    
    public function getValues()
    {
        return $this->getWorkbench()->getComponentRegistry()->getComponentKeys();
    }

    public function getLabels()
    {
        return $this->getWorkbench()->getComponentRegistry()->getComponentNames();
    }
    
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