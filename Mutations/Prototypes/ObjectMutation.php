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
    private ?string         $changedName = null;
    private ?string         $changedDescription = null;
    private ?array          $attributeMutations = null;
    private ?UxonObject     $attributeMutationsUxon = null;

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only instances of pages supported!');
        }

        /* @var $subject \exface\Core\CommonLogic\Model\MetaObject */
        if (null !== $val = $this->getChangeName()) {
            $subject->setName($val);
        }
        if (null !== $val = $this->getChangeDescription()) {
            $subject->setShortDescription($val);
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
     * @return string|null
     */
    protected function getChangeName(): ?string
    {
        return $this->changedName;
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
        $this->changedName = $objectName;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getChangeDescription(): ?string
    {
        return $this->changedDescription;
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
        $this->changedDescription = $objectDescription;
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
     * @uxon-type \exface\Core\Mutations\Prototypes\ObjectAttributeMutation
     * @uxon-template [{"attribute_alias":"", "": ""}]
     *
     * @param array $attributeMutations
     * @return ObjectMutation
     */
    protected function setChangeAttributes(UxonObject $arrayOfMutations): ObjectMutation
    {
        $this->attributeMutations = null;
        $this->attributeMutationsUxon = $arrayOfMutations;
        return $this;
    }
}