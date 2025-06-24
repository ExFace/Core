<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;
use exface\Core\Mutations\AppliedEmptyMutation;

/**
 * Allows to modify the model of an object
 *
 * @author Andrej Kabachnik
 */
class ObjectMutation extends AbstractMutation
{
    private ?array          $attributeMutations = null;
    private ?UxonObject     $attributeMutationsUxon = null;
    private ?UxonObject $dataAddressPropertiesMutationUxon = nulL;
    private ?GenericUxonMutation $dataAddressPropertiesMutation = nulL;
    private array $changedObjects = [];

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only instances of pages supported!');
        }

        /* @var $subject \exface\Core\CommonLogic\Model\MetaObject */
        if (null !== $mutation = $this->getDataAddressPropertiesMutation()) {
            $uxon = $subject->getDataAddressProperties();
            $mutation->apply($uxon);
            $subject->setDataAddressProperties($uxon);
        }

        $objectChanges = $this->getObjectChanges();
        if (!empty($objectChanges)) {
            $subject->importUxonObject(new UxonObject($objectChanges));
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
        return $this->changedObjects;
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
        $this->changedObjects['name'] = $objectName;
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
        $this->changedObjects['description'] = $objectDescription;
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
        $this->changedObjects['dataAddress'] = $objectDataAddress;
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
        $this->changedObjects['readable'] = $objectReadableFlag;
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
        $this->changedObjects['writable'] = $objectWritableFlag;
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
}