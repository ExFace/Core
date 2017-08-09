<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Widgets\Button;
use exface\Core\CommonLogic\UxonObject;

trait iHaveButtonsAndToolbarsTrait 
{
    use iHaveToolbarsTrait;
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::addButton()
     */
    public function addButton(Button $button_widget, $index = null)
    {
        $this->getToolbarMain()->addButton($button_widget, $index);
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::removeButton()
     */
    public function removeButton(Button $button_widget)
    {
        $this->getToolbarMain()->removeButton($button_widget);
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonIndex()
     */
    public function getButtonIndex(Button $button)
    {
        return $this->getToolbarMain()->getButtonIndex($button);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButton()
     */
    public function getButton($index)
    {
        return $this->getToolbarMain()->getButton($index);
    }
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtons()
     */
    public function getButtons(callable $filter_callback = null)
    {
        $buttons = [];
        foreach ($this->getToolbars() as $toolbar){
            $buttons = array_merge($buttons, $toolbar->getButtons($filter_callback));
        }
        return $buttons;
    }
    
    /**
     * Adds buttons to the widget via array of button widgets.
     * 
     * The array must contain widget objects with widget_type Button or any 
     * derivatives. The widget_type canalso be ommitted. It is a good idea to 
     * only specify an explicit widget type if a special button(e.g. MenuButton) 
     * is required. For regular buttons it is advisable to let ExFache choose 
     * the right type.
     * 
     * All buttons specified here will be added to the main toolbar of the widget.
     * Refer to the description of the toolbars-property for details.
     * 
     * Depending on the align-attribute of each button it will be automatically
     * added to the first button group left or right in the main toolbar.
     * 
     * Example:
     *  {
     *      "buttons": [
     *          {
     *              "action_alias": "exface.Core.CreateObjectDialog"
     *          },
     *          {
     *              "widget_type": "MenuButton",
     *              "caption": "My menu",
     *              "buttons": [...]
     *          },
     *          {
     *              "action_alias": "exface.Core.RefreshWidget",
     *              "align": "right"
     *          }
     *      ]
     *  }
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * 
     * @param array $buttons
     * @return \exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait
     */
    public function setButtons(array $buttons)
    {
        $this->getToolbarMain()->setButtons($buttons);
        return $this;
    }
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::hasButtons()
     */
    public function hasButtons()
    {
        foreach ($this->getToolbars() as $toolbar){
            if ($toolbar->hasButtons()){
                return true;
            }
        }
        return false;
    }
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::countButtons()
     */
    public function countButtons(callable $filter_callback = null)
    {
        $cnt = 0;
        foreach ($this->getToolbars() as $toolbar){
            $cnt += $toolbar->countButtons($filter_callback);
        }
        return $cnt;
    }
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return $this->getToolbarMain()->getButtonWidgetType();
    }
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::createButton()
     */
    public function createButton(UxonObject $uxon = null)
    {
        return $this->getToolbarMain()->createButton($uxon);
    }
}