<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Widgets\Button;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

trait iHaveButtonsAndToolbarsTrait 
{
    use iHaveToolbarsTrait {
        initMainToolbar as initMainToolbarViaTrait;
    }
    
    private $buttonsPropertyValue = null;
    
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
     * All buttons specified here will be added to the main toolbar of the step.
     * Refer to the description of the `toolbars` property for details.
     *
     * Depending on the `align` property of each button it will be automatically
     * added to the first button group left or right in the main toolbar.
     * 
     * ## Example:
     * 
     * ```
     *  {
     *      "buttons": [
     *          {
     *              "action_alias": "exface.Core.ShowObjectCreateDialog"
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
     * ```
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-template [{"action_alias": ""}]
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons($buttons)
    {
        if ($this->hasToolbars() === false) {
            // Do not instantiate the main toolbar right away, just save the buttons for now and
            // use them once the toolbars are actually requested - see getToolbars().
            // This "lazy loading" helps save instantiating buttons in widget implementations
            // where the toolbars are never shown.
            $this->buttonsPropertyValue = $buttons;
        } else {
            $this->getToolbarMain()->setButtons($buttons);
        }
        return $this;
    }
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::hasButtons()
     */
    public function hasButtons()
    {
        if (empty($this->toolbars) === true && $this->buttonsPropertyValue === null) {
            return false;
        }
        
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
        if ($this->hasButtons() === false) {
            return 0;
        }
        
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
    
    /**
     * 
     * {@inheritdoc}
     * @see iHaveToolbarsTrait::getToolbars()
     */
    protected function initMainToolbar()
    {
        $tb = $this->initMainToolbarViaTrait();
        if ($this->buttonsPropertyValue !== null) {
            $tb->setButtons($this->buttonsPropertyValue);
            $this->buttonsPropertyValue = null;
        }
        return $tb;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::hasUidData()
     */
    public function hasUidData() : bool
    {
        switch (true) {
            case $this instanceof iHaveColumns:
                return $this->hasUidColumn();
            case $this instanceof iUseData:
                return $this->getData()->hasUidColumn();
            case $this instanceof iContainOtherWidgets:
                if ($this->getMetaObject()->hasUidAttribute()) {
                    if (! empty($this->findChildrenByAttribute($this->getMetaObject()->getUidAttribute()))) {
                        return true;
                    }
                }
                break;
        }
        return false;
    }
}