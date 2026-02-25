<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Factories\DataPointerFactory;

/**
 * A dialog header is a special widget used to display a summary of a dialog - typically the object currently loaded.
 * 
 * The `header` of a dialog has a `title_attribute_alias`, that can be used to display a primary (big) heading and
 * `widgets` to display additional information. You can explicitly hide the title using `hide_caption`.
 * 
 * ## Header widgets
 * 
 * It is a good idea to add a couple of `WidgetGroup` containers to the header to organize its widgets in groups 
 * for related information. If you have more than two widgets, put them into `WidgetGroup` containers.
 * 
 * The header is primarily intended for display widgets. If you omit the `widget_type`, the widget will use the
 * default display widget of the attribute or the generic `Display` as ultimate fallback. You can manually switch 
 * to other widget types like `ProgressBar`, `ColorIndicator`, etc. 
 * 
 * ## Headers do not provide data for actions by default
 * 
 * Keep in mind, that display widgets (which are rendered in the header by default) will not provide input data for 
 * the dialog actions! If you need data from the header in your action, use `InputHidden` or another input widget 
 * explicitly.
 * 
 * ## Header buttons
 * 
 * Dialog headers can have their own buttons. They will get the same input data as regular dialog buttons will, but
 * header buttons will never close the dialog. Technically, they are regular `Button` widgets, not `DialogButton`.
 * 
 * If you need a header button to close the dialog, switch set the `widget_type` to `DialogButton` manually.
 * 
 * Where these buttons are positioned and how they look like depends on the facade used - see `look&feel` section below.
 * 
 * ## Look&feel
 * 
 * The exact look&feel of a header depends on the facade being used, but most of them will show the header as a more
 * "compact" area on top of the dialog - often with a different background color.
 *     
 * @author Andrej Kabachnik
 *        
 */
class DialogHeader extends Form
{
    private $autogenerate = null;
    
    private $title_attribute_alias = null;
    
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
        $dataSheet = parent::prepareDataSheetToRead($dataSheet);
        if ($dataSheet->getMetaObject()->is($this->getMetaObject()) && $this->isTitleBoundToAttribute()) {
            $dataSheet->getColumns()->addFromAttribute($this->getTitleAttribute());
        }
        return $dataSheet;
    }
    
    protected function doPrefill(DataSheetInterface $dataSheet)
    {
        if ($this->isTitleBoundToAttribute()) {
            if ($col = $dataSheet->getColumns()->getByAttribute($this->getTitleAttribute())) {
                $cnt = count(array_unique($col->getValues()));
                if ($cnt > 1) {
                    if ($this->getCaption() === null) {
                        $this->setCaption($cnt . 'x ' . $this->getMetaObject()->getName());
                    }
                    $pointer = DataPointerFactory::createFromColumn($col);
                } else {
                    if ($this->getCaption() === null) {
                        $this->setCaption($this->getMetaObject()->getName() . ' ' . $col->getCellValue(0));
                    }
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