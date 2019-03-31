<?php
namespace exface\Core\Widgets;

/**
 * Special toolbar for Form widgets (by default every toolbar in a Form is a FormToolbar).
 * 
 * @method Form getInputWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class FormToolbar extends Toolbar
{
    /**
     * 
     * @return \exface\Core\Widgets\Form
     */
    public function getFormWidget()
    {
        return $this->getInputWidget();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::addButton()
     */
    public function addButton(Button $button_widget, $index = null)
    {
        parent::addButton($button_widget, $index);
        $form = $this->getFormWidget();
        $form->addRequiredWidgets($button_widget);
        return $this;
    }
    
    /**
     * Array of button widgets to be placed in the toolbar.
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-facade [{"action_alias": ""}]
     * 
     * Since buttons are not neccessarily added using FormToolbar::addButton(), but can be
     * added to enclosed button groups directly, the check for required widgets in the form
     * must be explicitly performed here for every button.
     * 
     * IDEA Auto-adding system widgets required for a button only works if the button is
     * added to the form toolbar or the form directly, but not if it is added to a button
     * goup enclosed. Perhaps there is some way to automate this too?
     * 
     * @see \exface\Core\Widgets\Toolbar::setButtons()
     */
    public function setButtons($buttons)
    {
        parent::setButtons($buttons);
        foreach ($this->getButtons() as $btn) {
            $this->getFormWidget()->addRequiredWidgets($btn);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'Button';
    }
}
