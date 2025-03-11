<?php

namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\Behaviors\CustomAttributeDefinitionBehavior;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Defines, where the definitions of the custom attributes are stored
 * 
 * @author Andrej Kabachnik
 */
class CustomAttributesDefinition implements iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;
    private ?BehaviorInterface $behavior = null;
    private ?MetaObjectInterface $definitionObject = null;
    private ?UxonObject $definitionFiltersUxon = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Model\BehaviorInterface $behavior
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     */
    public function __construct(BehaviorInterface $behavior, UxonObject $uxon)
    {
        $this->behavior = $behavior;
        $this->importUxonObject($uxon);
    }

    /**
     * 
     * @return BehaviorInterface
     */
    public function getStorageBehavior() : BehaviorInterface
    {
        return $this->behavior;
    }

    public function getDefinitionBehavior() : CustomAttributeDefinitionBehavior
    {
        $definitionBehavior = $this->getDefinitionsObject()->getBehaviors()->findBehavior(CustomAttributeDefinitionBehavior::class);
        if(! $definitionBehavior instanceof CustomAttributeDefinitionBehavior) {
            throw new BehaviorConfigurationError(
                $this->getStorageBehavior(),
                'Could not find behavior of type "' . CustomAttributeDefinitionBehavior::class . '" on MetaObject "' . $definitionObjectAlias . '"!',
            );
        }
        return $definitionBehavior;
    }

    /**
     * 
     * @return MetaObjectInterface
     */
    public function getStorageObject() : MetaObjectInterface
    {
        return $this->behavior->getObject();
    }

    /**
     * Returns the meta object, where the custom attributes are defined
     * 
     * @return MetaObjectInterface
     */
    public function getDefinitionsObject() : MetaObjectInterface
    {
        return $this->definitionObject;
    }

    /**
     * The object, that contains the definitions of the custom attributes and has a CustomAttributeDefinitionBehavior
     * 
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     * @uxon-required true
     * 
     * @param string $aliasWithNamespace
     * @return CustomAttributesDefinition
     */
    protected function setObjectAlias(string $aliasWithNamespace) : CustomAttributesDefinition
    {
        $this->definitionObject = MetaObjectFactory::createFromString($this->getStorageBehavior()->getWorkbench(), $aliasWithNamespace);
        return $this;
    }

    /**
     * 
     * @return UxonObject
     */
    public function exportUxonObject() : UxonObject
    {
        $uxon = new UxonObject([
            'object_alias' => $this->getDefinitionsObject()->getAliasWithNamespace()
        ]);
        return $uxon;
    }

    /**
     * Apply filters when reading custom attribute definitions.
     * 
     * @uxon-property filters
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"object_alias": "", "operator": "AND","conditions":[{"expression": "","comparator": "==","value": ""}]}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesDefinition
     */
    protected function setFilters(UxonObject $uxon) : CustomAttributesDefinition
    {
        $this->definitionFiltersUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject|null
     */
    protected function getFiltersUxon() : ?UxonObject
    {
        return $this->definitionFiltersUxon;
    }

    /**
     * 
     * @return UxonObject
     */
    public function getDataSheetTemplateUxon() : UxonObject
    {
        $uxon = new UxonObject([
            'object_alias' => $this->getDefinitionsObject()->getAliasWithNamespace()
        ]);

        if (null !== $val = $this->getFiltersUxon()) {
            $uxon->setProperty('filters', $val);
        }

        return $uxon;
    }
}