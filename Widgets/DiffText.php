<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\NotImplementedError;

/**
 * The DiffText widget compares two texts - an original and a new one - an shows a report highlighting the changes.
 * This widget
 * is especially usefull since all objects in ExFace can be converted to a UXON text representation, which can be compared using
 * this widget.
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

    public function setLeftAttributeAlias($value)
    {
        $this->left_attribute_alias = $value;
        return $this;
    }

    public function getRightAttributeAlias()
    {
        return $this->right_attribute_alias;
    }

    public function setRightAttributeAlias($value)
    {
        $this->right_attribute_alias = $value;
        return $this;
    }

    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the values are already set explicitly (e.g. a fixed value)
        if ($this->getLeftValue() && $this->getRightValue()) {
            return;
        }
        
        if ($this->getMetaObjectId() == $data_sheet->getMetaObject()->getId()) {
            $this->setLeftValue($data_sheet->getCellValue($this->getLeftAttributeAlias(), 0));
            $this->setRightValue($data_sheet->getCellValue($this->getRightAttributeAlias(), 0));
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
        
        if ($this->getMetaObjectId() == $data_sheet->getMetaObject()->getId()) {
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
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
    {
        // Do not request any prefill data, if the values are already set explicitly (e.g. a fixed value)
        if ($this->getLeftValue() && $this->getRightValue()) {
            return $data_sheet;
        }
        
        return $this->prepareDataSheetToRead($data_sheet);
    }
}
?>