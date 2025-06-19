<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
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
    private ?string $changedName = null;
    private ?string $changedDescription = null;
    private ?AbstractDataType $changedDataType = null;
    private ?string $changedDataAddress = null;
    private ?string $changedCalculation = null;
    private ?bool $changedReadable = null;
    private ?bool $changedWritable = null;
    private ?bool $changedEditable = null;
    private ?bool $changedHidden = null;
    private ?bool $changedRequired = null;
    private ?bool $changedSortable = null;
    private ?bool $changedFilterable = null;
    private ?string $changedDefaultValue = null;
    private ?string $changedFixedValue = null;
    private ?string $changedValueListDelimiter = null;
    private ?int $changedDefaultDisplayOrder = null;
    private ?string $changedDefaultSorterDir = null;

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only instances of pages supported!');
        }

        /* @var $subject \exface\Core\CommonLogic\Model\Attribute */
        if (null !== $val = $this->getChangeName()) {
            $subject->setName($val);
        }
        if (null !== $val = $this->getChangeDescription()) {
            $subject->setShortDescription($val);
        }
        if (null !== $val = $this->getChangeDataType()) {
            $subject->setDataType($val);
        }
        if (null !== $val = $this->getChangeDataAddress()) {
            $subject->setDataAddress($val);
        }
        if (null !== $val = $this->getChangeCalculation()) {
            $subject->setCalculation($val);
        }
        if (null !== $val = $this->getChangeReadable()) {
            $subject->setReadable($val);
        }
        if (null !== $val = $this->getChangeWritable()) {
            $subject->setWritable($val);
        }
        if (null !== $val = $this->getChangeEditable()) {
            $subject->setEditable($val);
        }
        if (null !== $val = $this->getChangeHidden()) {
            $subject->setHidden($val);
        }
        if (null !== $val = $this->getChangeRequired()) {
            $subject->setRequired($val);
        }
        if (null !== $val = $this->getChangeSortable()) {
            $subject->setSortable($val);
        }
        if (null !== $val = $this->getChangeFilterable()) {
            $subject->setFilterable($val);
        }
        if (null !== $val = $this->getChangeDefaultValue()) {
            $subject->setDefaultValue($val);
        }
        if (null !== $val = $this->getChangeFixedValue()) {
            $subject->setFixedValue($val);
        }
        if (null !== $val = $this->getChangeValueListDelimiter()) {
            $subject->setValueListDelimiter($val);
        }
        if (null !== $val = $this->getChangeDefaultDisplayOrder()) {
            $subject->setDefaultDisplayOrder($val);
        }
        if (null !== $val = $this->getChangeDefaultSorterDir()) {
            $subject->setDefaultSorterDir($val);
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
    protected function getChangeName(): ?string
    {
        return $this->changedName;
    }

    /**
     * Change the name of the attribute
     *
     * @uxon-property change_name
     * @uxon-type string
     *
     * @param string $attributeName
     * @return $this
     */
    protected function setChangeName(string $attributeName): ObjectAttributeMutation
    {
        $this->changedName = $attributeName;
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
     * Change the description (hint) for the attribute
     *
     * @uxon-property change_description
     * @uxon-type string
     *
     * @param string $attributeDescription
     * @return $this
     */
    protected function setChangeDescription(string $attributeDescription): ObjectAttributeMutation
    {
        $this->changedDescription = $attributeDescription;
        return $this;
    }

    /**
     * @return AbstractDataType|null
     */
    protected function getChangeDataType(): ?AbstractDataType
    {
        return $this->changedDataType;
    }

    /**
     * Change the data type of the attribute
     *
     * @uxon-property change_data_type
     * @uxon-type \exface\Core\CommonLogic\DataTypes\AbstractDataType
     * @uxon-template {"alias": ""}
     *
     * @param AbstractDataType $attributeDataType
     * @return $this
     */
    protected function setChangeDataType(AbstractDataType $attributeDataType): ObjectAttributeMutation
    {
        $this->changedDataType = $attributeDataType;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getChangeDataAddress(): ?string
    {
        return $this->changedDataAddress;
    }

    /**
     * Changes the data address of the attribute
     *
     * @uxon-property change_data_address
     * @uxon-type metamodel:datatype
     *
     * @param string $attributeDataAddress
     * @return $this
     */
    protected function setChangeDataAddress(string $attributeDataAddress): ObjectAttributeMutation
    {
        $this->changedDataAddress = $attributeDataAddress;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getChangeCalculation(): ?string
    {
        return $this->changedCalculation;
    }

    /**
     * Changes the calculation of the attribute
     *
     * @uxon-property change_calculation
     * @uxon-type string
     *
     * @param string $attributeCalculation
     * @return $this
     */
    protected function setChangeCalculation(string $attributeCalculation): ObjectAttributeMutation
    {
        $this->changedCalculation = $attributeCalculation;
        return $this;
    }

    /**
     * @return bool|null
     */
    protected function getChangeReadable(): ?bool
    {
        return $this->changedReadable;
    }

    /**
     * Changes the readable flag of the attribute
     *
     * @uxon-property change_readable
     * @uxon-type boolean
     *
     * @param bool $attributeReadable
     * @return $this
     */
    protected function setChangeReadable(bool $attributeReadable): ObjectAttributeMutation
    {
        $this->changedReadable = $attributeReadable;
        return $this;
    }

    /**
     * @return bool|null
     */
    protected function getChangeWritable(): ?bool
    {
        return $this->changedWritable;
    }

    /**
     * Changes the writable flag of the attribute
     *
     * @uxon-property change_writable
     * @uxon-type boolean
     *
     * @param bool $attributeWritable
     * @return $this
     */
    protected function setChangeWritable(bool $attributeWritable): ObjectAttributeMutation
    {
        $this->changedWritable = $attributeWritable;
        return $this;
    }

    /**
     * @return bool|null
     */
    protected function getChangeEditable(): ?bool
    {
        return $this->changedEditable;
    }

    /**
     * Changes the editable flag of the attribute
     *
     * @uxon-property change_editable
     * @uxon-type boolean
     *
     * @param bool $attributeEditable
     * @return $this
     */
    protected function setChangeEditable(bool $attributeEditable): ObjectAttributeMutation
    {
        $this->changedEditable = $attributeEditable;
        return $this;
    }

    /**
     * @return bool|null
     */
    protected function getChangeHidden(): ?bool
    {
        return $this->changedHidden;
    }

    /**
     * Changes the hidden flag of the attribute
     *
     * @uxon-property change_hidden
     * @uxon-type boolean
     *
     * @param bool $attributeHidden
     * @return $this
     */
    protected function setChangeHidden(bool $attributeHidden): ObjectAttributeMutation
    {
        $this->changedHidden = $attributeHidden;
        return $this;
    }

    /**
     * @return bool|null
     */
    protected function getChangeRequired(): ?bool
    {
        return $this->changedRequired;
    }

    /**
     * Changes the required flag of the attribute
     *
     * @uxon-property change_required
     * @uxon-type boolean
     *
     * @param bool $attributeRequired
     * @return $this
     */
    protected function setChangeRequired(bool $attributeRequired): ObjectAttributeMutation
    {
        $this->changedRequired = $attributeRequired;
        return $this;
    }

    /**
     * @return bool|null
     */
    protected function getChangeSortable(): ?bool
    {
        return $this->changedSortable;
    }

    /**
     * Changes the sortable flag of the attribute
     *
     * @uxon-property change_sortable
     * @uxon-type boolean
     *
     * @param bool $attributeSortable
     * @return $this
     */
    protected function setChangeSortable(bool $attributeSortable): ObjectAttributeMutation
    {
        $this->changedSortable = $attributeSortable;
        return $this;
    }

    /**
     * @return bool|null
     */
    protected function getChangeFilterable(): ?bool
    {
        return $this->changedFilterable;
    }

    /**
     * Changes the filterable flag of the attribute
     *
     * @uxon-property change_filterable
     * @uxon-type boolean
     *
     * @param bool $attributeFilterable
     * @return $this
     */
    protected function setChangeFilterable(bool $attributeFilterable): ObjectAttributeMutation
    {
        $this->changedFilterable = $attributeFilterable;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getChangeDefaultValue(): ?string
    {
        return $this->changedDefaultValue;
    }

    /**
     * Changes the default value of the attribute
     *
     * @uxon-property change_default_value
     * @uxon-type string
     *
     * @param string $attributeDefaultValue
     * @return $this
     */
    protected function setChangeDefaultValue(string $attributeDefaultValue): ObjectAttributeMutation
    {
        $this->changedDefaultValue = $attributeDefaultValue;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getChangeFixedValue(): ?string
    {
        return $this->changedFixedValue;
    }

    /**
     * Changes the fixed value of the attribute
     *
     * @uxon-property change_fixed_value
     * @uxon-type string
     *
     * @param string $attributeFixedValue
     * @return $this
     */
    protected function setChangeFixedValue(string $attributeFixedValue): ObjectAttributeMutation
    {
        $this->changedFixedValue = $attributeFixedValue;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getChangeValueListDelimiter(): ?string
    {
        return $this->changedValueListDelimiter;
    }

    /**
     * Changes the value list delimiter of the attribute
     *
     * @uxon-property change_value_list_delimiter
     * @uxon-type string
     *
     * @param string $attributeValueListDelimiter
     * @return $this
     */
    protected function setChangeValueListDelimiter(string $attributeValueListDelimiter): ObjectAttributeMutation
    {
        $this->changedValueListDelimiter = $attributeValueListDelimiter;
        return $this;
    }

    /**
     * @return int|null
     */
    protected function getChangeDefaultDisplayOrder(): ?int
    {
        return $this->changedDefaultDisplayOrder;
    }

    /**
     * Changes the default display order of the attribute
     *
     * @uxon-property change_default_display_order
     *
     * @param int $attributeDefaultDisplayOrder
     * @return $this
     */
    protected function setChangeDefaultDisplayOrder(int $attributeDefaultDisplayOrder): ObjectAttributeMutation
    {
        $this->changedDefaultDisplayOrder = $attributeDefaultDisplayOrder;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getChangeDefaultSorterDir(): ?string
    {
        return $this->changedDefaultSorterDir;
    }

    /**
     * Changes the default sorter direction of the attribute
     *
     * @uxon-property change_default_sorter_dir
     * @uxon-type [ASC,DESC]
     * @uxon-template ASC
     *
     * @param string $attributeDefaultSorterDir
     * @return $this
     */
    protected function setChangeDefaultSorterDir(string $attributeDefaultSorterDir): ObjectAttributeMutation
    {
        $this->changedDefaultSorterDir = $attributeDefaultSorterDir;
        return $this;
    }
}