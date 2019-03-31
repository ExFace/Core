<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;

/**
 * The DiffText widget compares two texts and shows a report highlighting the changes.
 * 
 * The texts can either be static or bound to attributes in the meta model via 
 * `left_attribute_alias` and `right_attribute_alias`. 
 * 
 * Although the texts are called "left" and "right", their actual positioning 
 * depends on the facade.
 * 
 * @author Andrej Kabachnik
 *        
 */
class DiffText extends AbstractWidget
{
    private $left_attribute_alias = NULL;

    private $left_value = NULL;

    private $right_attribute_alias = NULL;

    private $right_value = NULL;

    public function getLeftValue()
    {
        return $this->left_value;
    }

    public function setLeftValue($value)
    {
        $this->left_value = $value;
        return $this;
    }

    public function getRightValue()
    {
        return $this->right_value;
    }

    public function setRightValue($value)
    {
        $this->right_value = $value;
        return $this;
    }

    public function getLeftAttributeAlias()
    {
        return $this->left_attribute_alias;
    }
    
    /**
     * The alias of the attribute, which shall be the source of the left text
     *
     * @uxon-property left_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return \exface\Core\Widgets\DiffText
     */
    public function setLeftAttributeAlias($value)
    {
        $this->left_attribute_alias = $value;
        return $this;
    }

    public function getRightAttributeAlias()
    {
        return $this->right_attribute_alias;
    }

    /**
     * The alias of the attribute, which shall be the source of the right text
     * 
     * @uxon-property right_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return \exface\Core\Widgets\DiffText
     */
    public function setRightAttributeAlias($value)
    {
        $this->right_attribute_alias = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::doPrefill($data_sheet)
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        
        // Do not do anything, if the values are already set explicitly (e.g. a fixed value)
        if ($this->getLeftValue() && $this->getRightValue()) {
            return;
        }
        
        if ($this->getMetaObject()->isExactly($data_sheet->getMetaObject())) {
            if ($col = $data_sheet->getColumns()->getByExpression($this->getLeftAttributeAlias())) {
                $pointer = DataPointerFactory::createFromColumn($col, 0);
                $this->setLeftValue($pointer->getValue());
                $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'left_value', $pointer));
            }
            if ($col = $data_sheet->getColumns()->getByExpression($this->getRightAttributeAlias())) {
                $pointer = DataPointerFactory::createFromColumn($col, 0);
                $this->setRightValue($pointer->getValue());
                $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'right_value', $pointer));
            }
        } else {
            throw new NotImplementedError('Prefilling DiffText with data sheets from related objects not implemented!');
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        if ($this->getMetaObject()->isExactly($data_sheet->getMetaObject())) {
            // If we are looking for attributes of the object of this widget, then just return the attribute_alias
            $data_sheet->getColumns()->addFromExpression($this->getLeftAttributeAlias());
            $data_sheet->getColumns()->addFromExpression($this->getRightAttributeAlias());
        } else {
            // Otherwise we are looking for attributes relative to another object
            if ($this->getMetaObject()->findRelation($data_sheet->getMetaObject())) {
                throw new NotImplementedError('Prefilling DiffText with data sheets from related objects not implemented!');
            }
        }
        
        return $data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        // Do not request any prefill data, if the values are already set explicitly (e.g. a fixed value)
        if ($this->getLeftValue() && $this->getRightValue()) {
            return $data_sheet;
        }
        
        return $this->prepareDataSheetToRead($data_sheet);
    }
}
?>