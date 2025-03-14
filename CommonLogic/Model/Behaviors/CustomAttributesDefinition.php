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
 * Defines, where the definitions of the custom attributes are stored.
 * 
 * All the different custom attributes behaviors automatically create additional attributes
 * for their objects. These behaviors also define how to read and write data of custom attributes,
 * so they are often referred to as storage-behaviors. The available custom attributes themselves
 * are defined in a separate object - i.e. how each attribute is called, what its type is, etc. 
 * That definition-object MUST have the `CustomAttributeDefinitionBehavior` attached to it. 
 * 
 * This configuration here is used in storage-behaviors to connect them with their respective
 * definition-object and its definition-behavior. 
 * 
 * In addition to the `meta_object` property pointing to the definition-object, you can also
 * define `filters` and `sorters`, that will be applied by the CustomAttributesDefinitionBehavior
 * when reading the attributes for this particular storage-behavior.
 * 
 * On definition-object may contain custom attributes for different target objects. In fact, there
 * may be apps, that will have a centralized global custom attribute list, where there is an explicit
 * relation to the target-object for each attribute. This will result in multiple storage-behaviors
 * pointing to a single definition-object. See the `CustomAttributeDefinitionBehavior` for more details.
 * 
 * @see \exface\Core\Behaviors\CustomAttributeDefinitionBehavior
 * 
 * @author Andrej Kabachnik
 */
class CustomAttributesDefinition implements iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;
    private ?BehaviorInterface $behavior = null;
    private ?MetaObjectInterface $definitionObject = null;
    private ?UxonObject $definitionFiltersUxon = null;
    private ?UxonObject $definitionSortersUxon = null;
    private ?UxonObject $definitionDefaults = null;

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
     * Returns the instance of a CustomAttributes behavior attached the object, that will receive these attributes.
     * 
     * @return BehaviorInterface
     */
    public function getStorageBehavior() : BehaviorInterface
    {
        return $this->behavior;
    }

    /**
     * Returns the instance of the CustomAttributeDefinitionBehavior, that is responsible for
     * instantiating custom attributes.
     * 
     * @throws \exface\Core\Exceptions\Behaviors\BehaviorConfigurationError
     * @return CustomAttributeDefinitionBehavior
     */
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
     * Returns the meta object, that will receive the custom attributes
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
     * @uxon-template {"operator":"AND","conditions":[{"expression": "","comparator": "==","value": ""}]}
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
     * Array of sorters to apply when reading custom attribute definitions.
     * 
     * @uxon-property sorters
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSorter[]
     * @uxon-template [{"attribute_alias": "","direction": "ASC"}]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesDefinition
     */
    protected function setSorters(UxonObject $uxon) : CustomAttributesDefinition
    {
        $this->definitionSortersUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject|null
     */
    protected function getSortersUxon() : ?UxonObject
    {
        return $this->definitionSortersUxon;
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
        if (null !== $val = $this->getSortersUxon()) {
            $uxon->setProperty('sorters', $val);
        }

        return $uxon;
    }

    /**
     * Allows to set default values for attribtues programmatically
     * 
     * Note: this is intentionally NOT a UXON property. The attribtue defaults should instead be UXON
     * properties of the respective storage-behaviors directly. This way, each storage-behavior can have
     * its own defaults and UXON autosuggests also assume, that the object (e.g. of the attribute groups)
     * is the storage-object and not the definition-object, that is the abse of this prototype class.
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesDefinition
     */
    public function setAttributeDefaults(UxonObject $uxon) : CustomAttributesDefinition
    {
        $this->definitionDefaults = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject|null
     */
    public function getAttributeDefaults() : ?UxonObject
    {
        return $this->definitionDefaults;
    }
}