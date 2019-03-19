<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * A dialog header is a special widget used to display a summary of a dialog - typically the object currently loaded.
 *     
 * @author Andrej Kabachnik
 *        
 */
class DialogHeader extends Form
{
    private $autogenerate = null;
    
    private $title_attribute_alias = null;
    
    private $title_widget = null;
    
    /**
     * @return boolean
     */
    public function getAutogenerate()
    {
        return $this->autogenerate;
    }

    /**
     * 
     * @param boolean $autogenerate
     * @return DialogHeader
     */
    public function setAutogenerate($autogenerate)
    {
        $this->autogenerate = BooleanDataType::cast($autogenerate);
        return $this;
    }

    /**
     * 
     * @return Dialog
     */
    public function getDialog()
    {
        return $this->getParent();
    }
    
    /**
     * 
     * @return string
     */
    public function getTitleAttributeAlias() : string
    {
        if ($this->title_attribute_alias === null) {
            $object = $this->getMetaObject();
            if ($object->hasLabelAttribute()) {
                $this->title_attribute_alias = $object->getLabelAttributeAlias();
            } elseif ($object->hasUidAttribute()) {
                $this->title_attribute_alias = $object->getUidAttributeAlias();
            } else {
                throw new WidgetConfigurationError($this, 'Cannot determine attribute for a dynamic title of widget ' . $this->getWidgetType() . '!');
            }
        }
        return $this->title_attribute_alias;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getTitleAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getTitleAttributeAlias());
    }
    
    /**
     * The attribute to show as dialog title.
     * 
     * @uxon-property title_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return \exface\Core\Widgets\DialogHeader
     */
    public function setTitleAttributeAlias($alias)
    {
        $this->title_attribute_alias = $alias;
        return $this;
    }
    
    public function isTitleBoundToAttribute() : bool
    {
        if ($this->title_attribute_alias !== null) {
            return true;
        }
        
        try {
            $this->getTitleAttributeAlias();
        } catch (WidgetConfigurationError $e) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $dataSheet = null) : DataSheetInterface
    {
        $sheet = parent::prepareDataSheetToPrefill($dataSheet);
        if ($dataSheet->getMetaObject()->is($this->getMetaObject()) && $this->isTitleBoundToAttribute()) {
            $sheet->getColumns()->addFromAttribute($this->getTitleAttribute());
        }
        return $sheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $dataSheet = null) : DataSheetInterface
    {
        $sheet = parent::prepareDataSheetToRead($dataSheet);
        if ($dataSheet->getMetaObject()->is($this->getMetaObject()) && $this->isTitleBoundToAttribute()) {
            $sheet->getColumns()->addFromAttribute($this->getTitleAttribute());
        }
        return $sheet;
    }
    
    protected function doPrefill(DataSheetInterface $dataSheet)
    {
        if ($this->isTitleBoundToAttribute()) {
            if ($col = $dataSheet->getColumns()->getByAttribute($this->getTitleAttribute())) {
                $cnt = count(array_unique($col->getValues()));
                if ($cnt > 1) {
                    $this->setCaption($cnt . 'x ' . $this->getMetaObject()->getName());
                    $pointer = DataPointerFactory::createFromColumn($col);
                } else {
                    $this->setCaption($col->getCellValue(0));
                    $pointer = DataPointerFactory::createFromColumn($col, 0);
                }
                $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'caption', $pointer));
            }
        }
        
        return parent::doPrefill($dataSheet);
    }
    
    public function isReadonly() : bool
    {
        return true;
    }
}
?>