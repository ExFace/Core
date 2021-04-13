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
use exface\Core\DataTypes\StringDataType;

/**
 * Opens a dialog modeled within the actions configuration.
 * 
 * **NOTE: ** `effects` defined for this action explicitly will be triggered __after__ the dialog is closed!
 * 
 * To define the dialog contents use either
 * 
 * - `widget` property defining the entire dialog widget (i.e. a widget of type `Dialog` or a derivate)
 * - `dialog` property to skip the definition of the dialog itself and simply define its configuration
 * (in this case the widget type will be `Dialog`).
 * 
 * If you are reusing a dialog (e.g. extend one) as the contents of this action, you can overwrite
 * its `buttons` by setting `dialog_buttons` in the action config.
 * 
 * You can force the `Dialog` to open regularly or maximized by via `maximize` property. The exact effect
 * of this property depends on the facade/template used for rendering and how it handles the `maximized`
 * property of dialog widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowDialog extends ShowWidget implements iShowDialog
{
    private $widget_was_enhanced = false;

    private $dialog_buttons_uxon = null;
    
    private $maximize = null;
    
    protected function init()
    {
        parent::init();
        $this->setPrefillWithFilterContext(true);
    }

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
        $dialog = WidgetFactory::createFromUxonInParent($parent_widget, $this->addIdSpaceToWidgetUxon(new UxonObject()), $this->getDefaultWidgetType());
        $dialog->setMetaObject($this->getMetaObject());
        
        if ($contained_widget) {
            $dialog->addWidget($contained_widget);
            $width = $contained_widget->getWidth();
            $heigth = $contained_widget->getHeight();
            if (! $width->isUndefined() && ! $width->isMax() && $width->getValue() !== '100%') {
                $dialog->setWidth($contained_widget->getWidth()->getValue());
            }
            if (! $heigth->isUndefined() && ! $heigth->isMax() && $heigth->getValue() !== '100%') {
                $dialog->setHeight($contained_widget->getHeight()->getValue());
            }
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
    public function getDefaultWidgetType() : ?string
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
     * Defines the dialog to show (same as the `widget` property).
     * 
     * @uxon-property dialog
     * @uxon-type \exface\Core\Widgets\Dialog
     * @uxon-template {"widgets": [{"": ""}]}
     * 
     * @param UxonObject|WidgetInterface $widget_or_uxon_object
     * @return ShowDialog
     */
    public function setDialog($widget_or_uxon_object) : ShowDialog
    {
        return $this->setWidget($widget_or_uxon_object);
    }

    /**
     * Adds extra buttons to a dialog.
     *
     * ```
     * "dialog_buttons": [
     *      {
     *          "widget_type": "DialogButton",
     *          "action_alias": "exface.Core.UpdateData",
     *          "caption": "Speichern"
     *      }
     *  ]
     *  
     * ```
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
     * Set to TRUE or FALSE to force the dialog to be maximized (or not).
     * 
     * If not set explicitly, it will be up to the default facade behavior, how the dialog
     * is opened.
     * 
     * @uxon-property maximize
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowDialog::setMaximize()
     */
    public function setMaximize($true_or_false)
    {
        $this->maximize = BooleanDataType::cast($true_or_false);
    }
    
    /**
     * By default, the content of a dialog get's a separate id_space to avoid conflicting
     * ids on a page with multiple dialogs. If this behavior is not intended, just set
     * an id_space in the root of the widget-UXON explicitly.
     * 
     * @see \exface\Core\Actions\ShowWidget::getWidgetUxon()
     */
    protected function getWidgetUxon()
    {
        if ($uxon = parent::getWidgetUxon()) {
            if ($uxon->isEmpty() === false && $uxon->hasProperty('id_space') === false) {
                // FIXME this causes errors if a dialog is defined directly in the button and has widget-links
                // within the dialog. But why? 
                $uxon = $this->addIdSpaceToWidgetUxon($uxon);
            }
        }
        return $uxon;
    }
    
    /**
     * This method automatically adds an id_space to the given UXON, so that all widgets within
     * are created in an isolated id space and widget links inside the dialog still work even if 
     * multiple dialogs with the same ids are located in the same page (e.g. if multiple actions
     * inherit the same widget or use the default editor of the same object).
     * 
     * For example, concider the following dialog widget:
     * 
     * ```
     * {
     *  "widget_type": "Dialog",
     *  "widgets": [
     *      {"widget_type": "DataTable", "id": "my_table"}
     *  ],
     *  "buttons": [
     *      {"input_widget_id": "my_table"}
     *  ]
     * }
     * 
     * ```
     * 
     * Placing the entire dialog in an id space makes sure, the id `my_table` will never conflict
     * with any other ids on the page. Even if there is another `my_table`, ours only has to be
     * unique within the id space of the dialog. Using the id of the action's trigger (button)
     * is simply an easy way to make sure the id space is unique itself.
     * 
     * However, just the using the id of the trigger is not enough as it may also have a custom
     * id space. We need to take that into account too.
     * 
     * For example, we could place another button in our dialog, that calls this action again.
     * The id space of the nested dialog would then be `id_space_of_first_dialog.id_of_trigger_button`,
     * which is unique again. 
     * 
     * @param UxonObject $uxon
     * @return UxonObject
     */
    protected function addIdSpaceToWidgetUxon(UxonObject $uxon) : UxonObject
    {
        if ($parent = $this->getWidgetDefinedIn()) {
            $parentId = $parent->getId();
            $parentSpace = $parent->getIdSpace();
            $sep = $parent->getPage()->getWidgetIdSpaceSeparator();
            // If some of the parents already had their id spaces prepended to the ids,
            // we should not prepend the id space again - otherwise it's doubled!
            if ($parentSpace && StringDataType::startsWith($parentId, $parentSpace . $sep) === false) {
                $idSpace = $parentSpace . $sep . $parentId;
            } else {
                $idSpace = $parentId;
            }
            $uxon->setProperty('id_space', $idSpace);
        }
        return $uxon;
    }
}