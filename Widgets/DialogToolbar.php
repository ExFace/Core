<?php
namespace exface\Core\Widgets;

/**
 * A special toolbar widget for Dialogs (by default all toolbars in a dialog are DialogToolbars).
 * 
 * @method Dialog getInputWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class DialogToolbar extends FormToolbar
{
    /**
     * 
     * @return Dialog
     */
    public function getDialogWidget()
    {
        return $this->getInputWidget();
    }
    
    public function getButtonWidgetType()
    {
        return 'DialogButton';
    }
}
