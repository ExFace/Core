<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;
use exface\Core\Mutations\AppliedMutation;

/**
 * Allows to modify the model of an attribute
 *
 * @author Andrej Kabachnik
 */
class ObjectAttributeMutation extends AbstractMutation
{
    private ?string $attributeAlias = null;
    private ?UxonObject $changedDataTypeUxon = null;
    private ?GenericUxonMutation $changedDataTypeMutation = null;
    private ?UxonObject $changedDefaultEditorUxon = null;
    private ?GenericUxonMutation $changedDefaultEditorMutation = null;
    private ?UxonObject $changedDefaultDisplayUxon = null;
    private ?GenericUxonMutation $changedDefaultDisplayMutation = null;
    private array $changedAttributes = [];

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only instances of pages supported!');
        }

        /* @var $subject \exface\Core\CommonLogic\Model\Attribute */
        $stateBefore = null;
        if ($this->hasChanges()) {
            $stateBefore = $subject->exportUxonObject()->toJson(true);
        }

        if (null !== $mutation = $this->getChangeDataTypeMutation()) {
            // Get the current data type customization UXON from the attribute.
            // DO NOT use $subject->getDataType()->exportUxonObject() here because may contain later changes or default
            // values from the data type. However, the creator of the mutation will have the attribute configuration
            // in mind, so the mutation must be applied to it instead of the resulting data type UXON.
            $uxon = $subject->getCustomDataTypeUxon();
            // Since the customization does not include a data type selector, add it here to make the UXON compatible
            // with Attribute::setDataType().
            $uxon->setProperty('alias', $subject->getDataType()->getAliasWithNamespace());
            $mutation->apply($uxon);
            $subject->setDataType($uxon);
        }
        if (null !== $mutation = $this->getChangedDefaultEditorMutation()) {
            $uxon = $subject->getDefaultEditorUxon();
            $mutation->apply($uxon);
            $subject->setDefaultEditorUxon($uxon);
        }
        if (null !== $mutation = $this->getChangedDefaultDisplayMutation()) {
            $uxon = $subject->getDefaultDisplayUxon();
            $mutation->apply($uxon);
            $subject->setDefaultDisplayUxon($uxon);
        }

        $changes = $this->getAttributeChanges();
        if (!empty($changes)) {
            $subject->importUxonObject(new UxonObject($changes));
        }

        return new AppliedMutation($this, $subject, $stateBefore ?? '', ($stateBefore !== null ? $subject->exportUxonObject()->toJson(true) : ''));
    }

    /**
     * @see MutationInterface::supports()
     */
    public function supports($subject): bool
    {
        return $subject instanceof MetaAttributeInterface;
    }

    protected function getAttributeChanges(): array
    {
        return $this->changedAttributes;
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
        $this->changedAttributes['name'] = $attributeName;
        return $this;
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
        $this->changedAttributes['description'] = $attributeDescription;
        return $this;
    }

    /**
     * @return GenericUxonMutation|null
     */
    protected function getChangeDataTypeMutation(): ?GenericUxonMutation
    {
        if ($this->changedDataTypeMutation === null && $this->changedDataTypeUxon !== null) {
            $this->changedDataTypeMutation = new GenericUxonMutation($this->getWorkbench(), $this->changedDataTypeUxon);
        }
        return $this->changedDataTypeMutation;
    }

    /**
     * Change the data type of the attribute including its custom options
     *
     * @uxon-property change_data_type
     * @uxon-type \exface\Core\CommonLogic\DataTypes\AbstractDataType
     * @uxon-template {"alias": ""}
     *
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setChangeDataType(UxonObject $uxon): ObjectAttributeMutation
    {
        $this->changedDataTypeUxon = $uxon;
        $this->changedDataTypeMutation = null;
        return $this;
    }

    /**
     * @return GenericUxonMutation|null
     */
    protected function getChangedDefaultEditorMutation(): ?GenericUxonMutation
    {
        if ($this->changedDefaultEditorMutation === null && $this->changedDefaultEditorUxon !== null) {
            $this->changedDefaultEditorMutation = new GenericUxonMutation($this->getWorkbench(), $this->changedDefaultEditorUxon);
        }
        return $this->changedDefaultEditorMutation;
    }

    /**
     * Change the default editor widget for this attribute
     *
     * @uxon-property change_default_editor_uxon
     * @uxon-type \exface\Core\Widgets\Input
     * @uxon-template {"widget_type": ""}
     *
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setChangeDefaultEditorUxon(UxonObject $uxon):  ObjectAttributeMutation
    {
        $this->changedDefaultEditorUxon = $uxon;
        $this->changedDefaultEditorMutation = null;
        return $this;
    }

    /**
     * @return GenericUxonMutation|null
     */
    protected function getChangedDefaultDisplayMutation (): ?GenericUxonMutation
    {
        if ($this->changedDefaultDisplayMutation === null && $this->changedDefaultDisplayUxon !== null) {
            $this->changedDefaultDisplayMutation = new GenericUxonMutation($this->getWorkbench(), $this->changedDefaultDisplayUxon);
        }
        return  $this->changedDefaultDisplayMutation;
    }

    /**
     * Changes the default display widget to use for this attribute
     *
     * @uxon-property change_default_display_widget
     * @uxon-type \exface\Core\Widgets\Display
     * @uxon-template {"widget_type": ""}
     *
     * @param UxonObject $uxon
     * @return ObjectAttributeMutation
     */
    protected function setChangeDefaultDisplayWidget(UxonObject $uxon):  ObjectAttributeMutation
    {
        $this->changedDefaultDisplayUxon = $uxon;
        $this->changedDefaultDisplayMutation = null;
        return $this;
    }

    /**
     * Changes the data address of the attribute
     *
     * @uxon-property change_data_address
     * @uxon-type string
     *
     * @param string $attributeDataAddress
     * @return $this
     */
    protected function setChangeDataAddress(string $attributeDataAddress): ObjectAttributeMutation
    {
        $this->changedAttributes['dataAddress'] = $attributeDataAddress;
        return $this;
    }

    /**
     * Changes the calculation of the attribute
     *
     * @uxon-property change_calculation
     * @uxon-type metamodel:formula
     *
     * @param string $attributeCalculation
     * @return $this
     */
    protected function setChangeCalculation(string $attributeCalculation): ObjectAttributeMutation
    {
        $this->changedAttributes['calculation'] =  $attributeCalculation;
        return $this;
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
        $this->changedAttributes['readable'] = $attributeReadable;
        return $this;
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
        $this->changedAttributes['writable'] = $attributeWritable;
        return $this;
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
        $this->changedAttributes['editable'] = $attributeEditable;
        return $this;
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
        $this->changedAttributes['hidden'] = $attributeHidden;
        return $this;
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
        $this->changedAttributes['required'] = $attributeRequired;
        return $this;
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
        $this->changedAttributes['sortable'] = $attributeSortable;
        return $this;
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
        $this->changedAttributes['filterable'] = $attributeFilterable;
        return $this;
    }

    /**
     * Changes the default value of the attribute
     *
     * @uxon-property change_default_value
     * @uxon-type metamodel:formula|string|number
     *
     * @param string $attributeDefaultValue
     * @return $this
     */
    protected function setChangeDefaultValue(string $attributeDefaultValue): ObjectAttributeMutation
    {
        $this->changedAttributes['defaultValue'] = $attributeDefaultValue;
        return $this;
    }

    /**
     * Changes the fixed value of the attribute
     *
     * @uxon-property change_fixed_value
     * @uxon-type metamodel:formula
     *
     * @param string $attributeFixedValue
     * @return $this
     */
    protected function setChangeFixedValue(string $attributeFixedValue): ObjectAttributeMutation
    {
        $this->changedAttributes['fixedValue'] = $attributeFixedValue;
        return $this;
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
        $this->changedAttributes['valueListDelimiter'] = $attributeValueListDelimiter;
        return $this;
    }

    /**
     * Changes the default display order of the attribute
     *
     * @uxon-property change_default_display_order
     * @uxon-type integer
     *
     * @param int $attributeDefaultDisplayOrder
     * @return $this
     */
    protected function setChangeDefaultDisplayOrder(int $attributeDefaultDisplayOrder): ObjectAttributeMutation
    {
        $this->changedAttributes['defaultDisplayOrder'] = $attributeDefaultDisplayOrder;
        return $this;
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
        $this->changedAttributes['defaultSorterDir'] = $attributeDefaultSorterDir;
        return $this;
    }

    protected function hasChanges() : bool
    {
        return ! empty($this->changedAttributes) || null !== $this->changedDataTypeUxon || null !== $this->changedDefaultDisplayUxon || null !== $this->changedDefaultEditorUxon;
    }
}