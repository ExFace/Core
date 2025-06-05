<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;
use exface\Core\Mutations\AppliedEmptyMutation;

/**
 * Allows to modify the model of an attribute
 *
 * @author Andrej Kabachnik
 */
class ObjectAttributeMutation extends AbstractMutation
{
    private ?string $attributeAlias = null;
    private ?string $attributeName = null;
    private ?string $attributeDescription = null;

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only instances of pages supported!');
        }

        /* @var $subject \exface\Core\CommonLogic\Model\Attribute */
        if (null !== $val = $this->getAttributeName()) {
            $subject->setName($val);
        }
        if (null !== $val = $this->getAttributeDescription()) {
            $subject->setShortDescription($val);
        }

        return new AppliedEmptyMutation($this, $subject);
    }

    /**
     * @see MutationInterface::supports()
     */
    public function supports($subject): bool
    {
        return $subject instanceof MetaAttributeInterface;
    }

    /**
     * The alias of the attribute to modify
     *
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $alias
     * @return $this
     */
    protected function setAttributeAlias(string $alias): ObjectAttributeMutation
    {
        $this->attributeAlias = $alias;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAttributeAlias(): ?string
    {
        return $this->attributeAlias;
    }

    /**
     * @return string|null
     */
    protected function getAttributeName(): ?string
    {
        return $this->attributeName;
    }

    /**
     * Change the name of the attribute
     *
     * @uxon-property attribute_name
     * @uxon-type string
     *
     * @param string $attributeName
     * @return $this
     */
    protected function setAttributeName(string $attributeName): ObjectAttributeMutation
    {
        $this->attributeName = $attributeName;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getAttributeDescription(): ?string
    {
        return $this->attributeDescription;
    }

    /**
     * Change the description (hint) for the attribute
     *
     * @uxon-property attribute_description
     * @uxon-type string
     *
     * @param string $attributeDescription
     * @return $this
     */
    protected function setAttributeDescription(string $attributeDescription): ObjectAttributeMutation
    {
        $this->attributeDescription = $attributeDescription;
        return $this;
    }
}