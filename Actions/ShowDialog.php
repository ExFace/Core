<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;

/**
 * Renders a dialog with any contents specified in the widget-property.
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowDialog extends ShowWidget implements iShowDialog
{
    private $widget_was_enhanced = false;

    private $dialog_buttons_uxon = null;
    
    private $maximize = null;

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
    protected function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = NULL)
    {
        /* @var $dialog \exface\Core\Widgets\Dialog */
        $parent_widget = $this->getWidgetDefinedIn();
        $dialog = WidgetFactory::create($page, 'Dialog', $parent_widget);
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
        if ($this->getWidgetDefinedIn()) {
            if (! $dialog->getIcon() && ($this->getWidgetDefinedIn() instanceof iHaveIcon)) {
                $dialog->setIcon($this->getWidgetDefinedIn()->getIcon());
            }
        } else {
            if (! $dialog->getIcon()) {
                $dialog->setIcon($this->getIcon());
            }
        }
        
        if (! $dialog->getCaption()) {
            $dialog->setCaption($this->getDialogCaption());
        }
        
        if (! $this->getDialogButtonsUxon()->isEmpty()) {
            $dialog->setButtons($this->getDialogButtonsUxon());
        }
        
        if (! is_null($this->getMaximize(null))) {
            $dialog->setMaximized($this->getMaximize(null));
        }
        
        return $dialog;
    }

    protected function getDialogCaption()
    {
        if ($this->getWidgetDefinedIn()) {
            $caption = $this->getWidgetDefinedIn()->getCaption();
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
        if (is_null($widget)) {
            try {
                $page = $this->getWidgetDefinedIn()->getPage();
            } catch (\Throwable $e) {
                $page = UiPageFactory::createEmpty($this->getWorkbench());
            }
            $widget = $this->createDialogWidget($page);
            $this->setWidget($widget);
        }
        
        if (! ($widget instanceof Dialog)) {
            $widget = $this->createDialogWidget($widget->getPage(), $widget);
            $this->setWidget($widget);
        }
        
        if (! $this->widget_was_enhanced) {
            $widget = $this->enhanceDialogWidget($widget);
            $this->setWidget($widget);
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
     * 
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function getDialogButtonsUxon()
    {
        if (is_null($this->dialog_buttons_uxon)){
            $this->dialog_buttons_uxon = new UxonObject();
        }
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
     * @uxon-type \exface\Core\Widgets\DialogButton[]
     * @uxon-template [{"action_alias": ""}]
     *
     * @param UxonObject $uxon            
     * @return \exface\Core\Actions\ShowDialog
     */
    public function setDialogButtons(UxonObject $uxon)
    {
        $this->dialog_buttons_uxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowDialog::getMaximize()
     */
    public function getMaximize($default = false)
    {
        return is_null($this->maximize) ? $default : $this->maximize;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowDialog::setMaximize()
     */
    public function setMaximize($true_or_false)
    {
        $this->maximize = BooleanDataType::cast($true_or_false);
    }
}
?>