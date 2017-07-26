<?php
namespace exface\Core\Widgets;

/**
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
