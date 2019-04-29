<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iTriggerAction;

/**
 * This trait contains common methods to implement the iHaveContextualHelp interface.
 * 
 * @author Andrej Kabachnik
 */
trait iHaveContextualHelpTrait {
    
    private $help_button = null;
    
    private $help_button_uxon = null;
    
    private $hide_help_button = false;
        
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpButton()
     */
    public function getHelpButton() : iTriggerAction
    {
        if ($this->help_button === null) {
            $this->help_button = WidgetFactory::createFromUxonInParent($this, $this->getHelpButtonUxon(), $this->getButtonWidgetType());
        }
        return $this->help_button;
    }
    
    /**
     * 
     * @return UxonObject
     */
    private function getHelpButtonUxon() : UxonObject
    {
        return $this->help_button_uxon ?? new UxonObject([
            'hidden' => true,
            'refresh_input' => false,
            'action' => [
                'alias' => 'exface.Core.ShowHelpDialog'
            ]
        ]);
    }
    
    
    /**
     * Custom configuration for the contextual help button.
     * 
     * @uxon-property help_button
     * @uxon-type \exface\Core\Widgets\Button
     * @uxon-template {"action": {"alias": "exface.Core.ShowHelpDialog"}}
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::setHelpButton()
     */
    public function setHelpButton(UxonObject $uxon) : iHaveContextualHelp
    {
        $this->help_button_uxon = $uxon;
    }
    
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHideHelpButton()
     */
    public function getHideHelpButton() : bool
    {
        return $this->hide_help_button;
    }
    
    /**
     * Set to TRUE to remove the contextual help button.
     *
     * @uxon-property hide_help_button
     * @uxon-type boolean
     * @uxon-default false
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::setHideHelpButton()
     */
    public function setHideHelpButton(bool $value) : iHaveContextualHelp
    {
        $this->hide_help_button = $value;
        return $this;
    }
}