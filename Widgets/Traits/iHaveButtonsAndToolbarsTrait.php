<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Widgets\Button;

trait iHaveButtonsAndToolbarsTrait 
{
    use iHaveButtonsTrait;
    use iHaveToolbarsTrait;
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::addButton()
     */
    public function addButton(Button $button_widget)
    {
        $this->getToolbarMain()->addButton($button_widget);
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
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtons()
     */
    public function getButtons()
    {
        $buttons = [];
        foreach ($this->getToolbars() as $toolbar){
            $buttons = array_merge($buttons, $toolbar->getButtons());
        }
        return $buttons;
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
    
}