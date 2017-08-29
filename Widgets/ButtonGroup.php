<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Widgets\Traits\iUseInputWidgetTrait;
use exface\Core\Factories\WidgetFactory;

/**
 * A group of button widgets visually separated from the other buttons.
 *
 * Button groups are mostly used within toolbars and menus to create visual
 * boundaries around a set of buttons: in a menu there would be separators
 * around a button group, while in a toolbar a buttong group might have extra
 * space around it.
 * 
 * Button groups can be aligned within a toolbar. If you have a wide toolbar,
 * you can put some button groups to the left and others to the right.
 * 
 * @method iContainButtonGroups getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class ButtonGroup extends Container implements iHaveButtons, iCanBeAligned, iUseInputWidget
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }
    
    use iUseInputWidgetTrait;
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtons()
     */
    public function getButtons(callable $filter_callback = null)
    {
        return $this->getWidgets($filter_callback);
    }

    /**
     * Defines the contained buttons via array of button definitions.
     *
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons(array $buttons_array)
    {
        foreach ($buttons_array as $b) {
            if ($b instanceof Button){
                $button = $b;
            } elseif ($b instanceof UxonObject){
                $button = WidgetFactory::createFromUxon($this->getPage(), UxonObject::fromAnything($b), $this, $this->getButtonWidgetType());
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'Cannot use "' . gettype($b) . '" as button in ' . $this->getWidgetType() . '": instantiated button widget (or derivative) or corresponding UXON object expected!');
            }
            // Add the button to the group
            $this->addButton($button);
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::addButton()
     */
    public function addButton(Button $button_widget, $index = null)
    {
        return $this->addWidget($button_widget, $index);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::removeButton()
     */
    public function removeButton(Button $button_widget)
    {
        return $this->removeWidget($button_widget);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        if ($this->getParent() instanceof Toolbar){
            return $this->getParent()->getButtonWidgetType();
        } elseif (method_exists($this->getInputWidget(), 'getButtonWidgetType')){
            return $this->getInputWidget()->getButtonWidgetType();
        }
        return 'Button';
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::hasButtons()
     */
    public function hasButtons()
    {
        return $this->hasWidgets();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonIndex()
     */
    public function getButtonIndex(Button $widget)
    {
        return $this->getWidgetIndex($widget);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButton()
     */
    public function getButton($index)
    {
        return $this->getWidget($index);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::countButtons()
     */
    public function countButtons(callable $filter_callback = null)
    {
        return count($this->getButtons($filter_callback));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanBeAligned::getAlign()
     */
    public function getAlign()
    {
        if (! $this->isAlignSet()){
            foreach ($this->getButtons() as $btn){
                if ($btn->getAlign()){
                    $this->setAlign($btn->getAlign());
                }
                break;
            }
        }
        return $this->getAlignDefault();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::createButton()
     */
    public function createButton(UxonObject $uxon = null)
    {
        if (is_null($uxon)){
            return WidgetFactory::create($this->getPage(), $this->getButtonWidgetType(), $this);
        } else {
            return WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, $this->getButtonWidgetType());
        }
    }
}
?>