<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\CommonLogic\UxonObject;

class ShowDialog extends ShowWidget implements iShowDialog
{

    private $include_headers = true;

    private $widget_was_enhanced = false;

    private $dialog_buttons_uxon = [];

    /**
     * Creates the dialog widget.
     * If not contents is passed, an empty dialog widget will be returned.
     *
     * This method is called if there is no widget passed to the action or the passed widget is not a dialog.
     * It creates a basic dialog and optionally fills it with the given content. By overriding this method,
     * you can change the way non-dialog widgets are handled. To fill a dialog with default widgets, add
     * buttons, etc. override enhance_dialog_widget() instead.
     *
     * @see enhance_dialog_widget()
     * @return \exface\Core\Widgets\Dialog
     */
    protected function createDialogWidget(AbstractWidget $contained_widget = NULL)
    {
        /* @var $dialog \exface\Core\Widgets\Dialog */
        $parent_widget = $this->getCalledByWidget();
        $dialog = $this->getCalledOnUiPage()->createWidget('Dialog', $parent_widget);
        $dialog->setMetaObject($this->getMetaObject());
        
        if ($contained_widget) {
            $dialog->addWidget($contained_widget);
        }
        
        return $dialog;
    }

    /**
     * Adds some default attributes to a given dialog, that can be derived from the specifics of the action:
     * the dialog caption, icon, etc.
     * These attributes can thus be ommited, when manually defining a dialog
     * for the action.
     *
     * This method is called after the dialog widget had been instantiated - no matter how: from a UXON
     * description passed to the action or automatically using create_dialog_widget(). This is the main
     * difference to create_dialog_widget(), which is only called if no dialog was given.
     *
     * Override this method to enhance the dialog even further: add widgets, buttons, etc.
     *
     * @param Dialog $dialog            
     * @return \exface\Core\Widgets\Dialog
     */
    protected function enhanceDialogWidget(Dialog $dialog)
    {
        
        // If the widget calling the action (typically a button) is known, inherit some of it's attributes
        if ($this->getCalledByWidget()) {
            if (! $dialog->getIconName() && ($this->getCalledByWidget() instanceof iHaveIcon)) {
                $dialog->setIconName($this->getCalledByWidget()->getIconName());
            }
        } else {
            if (! $dialog->getIconName()) {
                $dialog->setIconName($this->getIconName());
            }
        }
        
        if (! $dialog->getCaption()) {
            $dialog->setCaption($this->getDialogCaption());
        }
        
        if (count($this->getDialogButtonsUxon()) > 0) {
            $dialog->setButtons($this->getDialogButtonsUxon());
        }
        
        return $dialog;
    }

    protected function getDialogCaption()
    {
        if ($this->getCalledByWidget()) {
            $caption = $this->getCalledByWidget()->getCaption();
        }
        if (! $caption) {
            $caption = $this->getName();
        }
        return $caption;
    }

    /**
     * The widget shown by ShowDialog is a dialog of course.
     * However, specifying the entire dialog widget for custom dialogs is a lot of work,
     * so you can also specify just the contents of the dialog in the widget property of the action in UXON. In this case, those widgets
     * specified there will be automatically wrapped in a dialog. This makes creating dialog easier and you can also reuse existing widgets,
     * that are no dialogs (for example an entire page can be easily show in a dialog).
     *
     * @see \exface\Core\Actions\ShowWidget::getWidget()
     */
    public function getWidget()
    {
        $widget = parent::getWidget();
        if (! ($widget instanceof Dialog)) {
            if (!is_null($widget)){
                $this->getWorkbench()->getLogger()->warning('Widget of type ' . $widget->getWidgetType() . ' used for action ' . $this->getAliasWithNamespace() . '! This is known to cause issues with AJAX requests: use dialog-widgets instead.');
            }
            $widget = $this->createDialogWidget($widget);
            $this->setWidget($widget);
        }
        
        if (! $this->widget_was_enhanced) {
            $widget = $this->enhanceDialogWidget($widget);
            $this->widget_was_enhanced = true;
        }
        return $widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::getDefaultWidgetType()
     */
    public function getDefaultWidgetType()
    {
        return 'Dialog';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowDialog::getDialogWidget()
     */
    public function getDialogWidget()
    {
        return $this->getWidget();
    }

    /**
     * The output for action showing dialogs is either the rendered contents of the dialog (if lazy loading is enabled)
     * or the rendered dialog itself.
     *
     * FIXME Remove outputting only the content of the dialog for ajax requests once all templates moved to fetching entire dialogs!
     *
     * @see \exface\Core\Actions\ShowWidget::getResultOutput()
     */
    public function getResultOutput()
    {
        $dialog = $this->getResult();
        
        $this->getResult()->setLazyLoading(false);
        if ($this->getIncludeHeaders()) {
            $code = $this->getTemplate()->drawHeaders($this->getResult());
        }
        $code .= parent::getResultOutput();
        
        return $code;
    }

    public function getIncludeHeaders()
    {
        return $this->include_headers;
    }

    public function setIncludeHeaders($value)
    {
        $this->include_headers = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getDialogButtonsUxon()
    {
        return $this->dialog_buttons_uxon;
    }

    /**
     * Adds extra buttons to a dialog.
     *
     * "dialog_buttons": [
     *      {
     *          "widget_type": "DialogButton",
     *          "action_alias": "exface.Core.UpdateData",
     *          "caption": "Speichern"
     *      }
     *  ]
     *  
     * @uxon-property dialog_buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     *
     * @param UxonObject[] $uxon_array            
     * @return \exface\Core\Actions\ShowDialog
     */
    public function setDialogButtons($uxon_array)
    {
        $this->dialog_buttons_uxon = $uxon_array;
        return $this;
    }
}
?>