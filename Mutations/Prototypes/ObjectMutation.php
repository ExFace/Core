<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Model\CustomAttribute;
use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;
use exface\Core\Mutations\AppliedEmptyMutation;

/**
 * Allows to modify the model of an object
 * 
 * ## Change object properties
 * 
 * - `name`,
 * - `description`,
 * - `writable`,
 * - `readable`
 * 
 * ## Change the data address
 * 
 * You can overwrite the `data_address` or change `data_address_properties`. Since the latter is a UXON with
 * a data source specific additional configuration, you do not overwrite it directly, but rather define
 * UXON mutation rules to change specific places in the data address properties. 
 *
 * ## Change attributes
 * 
 * Using `change_attributes` you can modify the model of any attribute of the model. See `ObjectAttributeMutation`
 * for more details.
 * 
 * ## Add custom attributes
 * 
 * You can add new attributes using `add_attributes`. For every attribute, you can freely set all its properties.
 * In particular, you can use regular data addresses for the data source of the object.
 * 
 * @author Andrej Kabachnik
 */
class ObjectMutation extends AbstractMutation
{
    private ?array                  $attributeMutations = null;
    private ?UxonObject             $attributeMutationsUxon = null;
    
    private ?UxonObject             $dataAddressPropertiesMutationUxon = nulL;
    private ?GenericUxonMutation    $dataAddressPropertiesMutation = nulL;
    
    private array                   $objectChanges = [];
    private ?UxonObject             $addedAttributesUxon = null;

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only instances of pages supported!');
        }

        /* @var $subject \exface\Core\CommonLogic\Model\MetaObject */
        if (null !== $val = ($changedObjects['name'] ?? null)) {
            $subject->setName($val);
        }
        if (null !== $val = ($changedObjects['description'] ?? null)) {
            $subject->setShortDescription($val);
        }
        if (null !== $val = ($changedObjects['data_address'] ?? null)) {
            $subject->setDataAddress($val);
        }
        if (null !== $mutation = $this->getDataAddressPropertiesMutation()) {
            $uxon = $subject->getDataAddressProperties();
            $mutation->apply($uxon);
            $subject->setDataAddressProperties($uxon);
        }
        if (null !== $val = ($changedObjects['readable'] ?? null)) {
            $subject->setReadable($val);
        }
        if (null !== $val = ($changedObjects['writable'] ?? null)) {
            $subject->setWritable($val);
        }

        foreach ($this->getAttributeMutations() as $mutation) {
            try {
                $alias = $mutation->getAttributeAlias();
                $attr = $subject->getAttribute($alias);
                $mutation->apply($attr);
            } catch (MetaAttributeNotFoundError $e) {
                throw new RuntimeException('Cannot apply mutation "' . $this->getName() . '". ' . $e->getMessage(), null, $e);
            }
        }
        
        if (null !== $uxonArray = $this->getCustomAttributesUxon()) {
            foreach ($uxonArray as $uxon) {
                $name = $uxon->getProperty('name');
                $alias = $uxon->getProperty('alias');
                if ($name === null) {
                    throw new UnexpectedValueException('Missing name for custom attribute "' . $alias . '" for mutation "' . $this->getName() . '"');
                }
                if ($alias === null) {
                    throw new UnexpectedValueException('Missing alias for custom attribute "' . $name . '" for mutation "' . $this->getName() . '"');
                }
                $attr = new CustomAttribute($subject, $name, $alias, $this);
                $attr->importUxonObject($uxon);
                $subject->addAttribute($attr);
            }
        }
        
        // TODO add attribute groups management here. Probably similarly to attributes also `change_attribute_groups`
        // and `add_attribute_groups`.

        return new AppliedEmptyMutation($this, $subject);
    }

    /**
     * @see MutationInterface::supports()
     */
    public function supports($subject): bool
    {
        return $subject instanceof MetaObjectInterface;
    }

    /**
     * returns an array of changed objects.
     *
     * @return array
     */
    protected function getObjectChanges(): array
    {
        return $this->objectChanges;
    }

    /**
     * Change the name of the object
     *
     * @uxon-property change_name
     * @uxon-type string
     *
     * @param string $objectName
     * @return $this
     */
    protected function setChangeName(string $objectName): ObjectMutation
    {
        $this->objectChanges['name'] = $objectName;
        return $this;
    }

    /**
     * Change the description (hint) for the object
     *
     * @uxon-property change_description
     * @uxon-type string
     *
     * @param string $objectDescription
     * @return $this
     */
    protected function setChangeDescription(string $objectDescription): ObjectMutation
    {
        $this->objectChanges['description'] = $objectDescription;
        return $this;
    }

    /**
     * @return ObjectAttributeMutation[]
     */
    protected function getAttributeMutations(): array
    {
        if ($this->attributeMutations === null) {
            $this->attributeMutations = [];
            if ($this->attributeMutationsUxon !== null) {
                foreach ($this->attributeMutationsUxon->getPropertiesAll() as $uxon) {
                    $this->attributeMutations[] = new ObjectAttributeMutation($this->getWorkbench(), $uxon);
                }
            }
        }
        return $this->attributeMutations;
    }

    /**
     * Mutations for attributes of the object
     *
     * @uxon-property change_attributes
     * @uxon-type \exface\Core\Mutations\Prototypes\ObjectAttributeMutation[]
     * @uxon-template [{"attribute_alias":"", "": ""}]
     *
     * @param UxonObject $arrayOfMutations
     * @return ObjectMutation
     */
    protected function setChangeAttributes(UxonObject $arrayOfMutations): ObjectMutation
    {
        $this->attributeMutations = null;
        $this->attributeMutationsUxon = $arrayOfMutations;
        return $this;
    }

    /**
     * Changes the data address of the object
     *
     * @uxon-property change_data_address
     * @uxon-type string
     *
     * @uxon-type string
     *
     * @param string $objectDataAddress
     * @return ObjectMutation|$this
     */
    protected function setChangeDataAddress(string $objectDataAddress): ObjectMutation
    {
        $this->objectChanges['data_address'] = $objectDataAddress;
        return $this;
    }

    /**
     * Changes the readable flag of the object
     *
     * @uxon-property change_readable
     * @uxon-type boolean
     *
     * @param bool $objectReadableFlag
     * @return ObjectMutation|$this
     */
    protected function setChangeReadable(bool $objectReadableFlag): ObjectMutation
    {
        $this->objectChanges['readable'] = $objectReadableFlag;
        return $this;
    }

    /**
     * Changes the writable flag of the object
     *
     * @uxon-property change_writable
     * @uxon-type boolean
     *
     * @param bool $objectWritableFlag
     * @return ObjectMutation|$this
     */
    protected function setChangeWritable(bool $objectWritableFlag): ObjectMutation
    {
        $this->objectChanges['writable'] = $objectWritableFlag;
        return $this;
    }

    /**
     * Modifies the data address properties of the object by applying UXON mutation rules
     *
     * @uxon-property change_data_address_properties
     * @uxon-type \exface\Core\Mutations\Prototypes\GenericUxonMutation
     * @uxon-template {"": ""}
     *
     * @param UxonObject $uxonMutation
     * @return $this
     */
    protected function setChangeDataAddressProperties(UxonObject $uxonMutation) : ObjectMutation
    {
        $this->dataAddressPropertiesMutationUxon = $uxonMutation;
        $this->dataAddressPropertiesMutation = null;
        return $this;
    }

    /**
     * @return GenericUxonMutation|null
     */
    protected function getDataAddressPropertiesMutation() : ?GenericUxonMutation
    {
        if ($this->dataAddressPropertiesMutation === null && $this->dataAddressPropertiesMutationUxon !== null) {
            $this->dataAddressPropertiesMutation = new GenericUxonMutation($this->getWorkbench(), $this->dataAddressPropertiesMutationUxon);
        }
        return $this->dataAddressPropertiesMutation;
    }
    
    protected function getCustomAttributesUxon() : ?UxonObject
    {
        return $this->addedAttributesUxon;
    }

    /**
     * Add custom attributes to the target object.
     * 
     * These attributes will be added as long as the mutation is active. Just as regular attributes, they
     * can have a data address, that points to their data in the data source of the object.
     * 
     * @uxon-property add_attributes
     * @uxon-type \exface\Core\CommonLogic\Model\CustomAttribute[]
     * @uxon-template [{"name": "", "alias": "", "data_address": "", "data_type": {"alias": ""}}]
     * 
     * @param UxonObject $arrayOfUxons
     * @return $this
     */
    protected function setAddAttributes(UxonObject $arrayOfUxons) : ObjectMutation
    {
        $this->addedAttributesUxon = $arrayOfUxons;
        return $this;
    }
}