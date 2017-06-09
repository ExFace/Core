<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iCanBeRequired;
use exface\Core\Interfaces\Widgets\iCanBeDisabled;

/**
 * This action will show a dialog displaying the default editor of a meta object in read-only mode.
 *
 * Dialogs that show meta objects, will use the default editor description from the object's model, if specified.
 * If not, the action will generate a generic editor listing all widgets with their default editors or respective
 * generic editors of the corresponding data types.
 *
 * If you do not specify a widget type in the object's default editor or set it to "Dialog", the UXON of the default
 * editor will be directly applied to the Dialog. If another widget type is specified, it will be treated as a separate
 * widget and added to the dialog as a child widget. Thus, if the default editor is
 *
 * {"widgets": [{...}, {...}], "caption": "My caption"}
 *
 * the caption of the dialog will be set to "My caption" and all the widgets will get appended to the dialog. On the
 * other hand, the following default editor will produce a single tabs widget, which will be appended to the generic
 * dialog:
 *
 * {"widget_type": "Tabs", "tabs": [...]}
 *
 * If you choose to customize the dialog directly (first example), you can ommit the "widgets" array completely. This
 * will case the default editor widgets to get generated and appended to your custom dialog. This is an easy way to
 * add custom buttons, captions, etc. to generic dialogs.
 *
 * @author Andrej Kabachnik
 *        
 */
class ShowObjectDialog extends ShowDialog
{

    private $show_only_editable_attributes = null;

    private $disable_editing = true;

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
        $this->setIconName('info');
        $this->setShowOnlyEditableAttributes(false);
        // Disable prefilling the widget from contexts as we only whant to fill in data that actually comes from the data source
        $this->setPrefillWithFilterContext(false);
    }

    /**
     * Create editors for all editable attributes of the object
     *
     * @return WidgetInterface[]
     */
    protected function createWidgetsForAttributes(AbstractWidget $parent_widget)
    {
        $editors = array();
        $cnt = 0;
        /* @var $attr \exface\Core\CommonLogic\Model\Attribute */
        foreach ($this->getMetaObject()->getAttributes() as $attr) {
            $cnt ++;
            // Ignore hidden attributes if they are not system attributes
            if ($attr->isHidden())
                continue;
            // Ignore not editable attributes if this feature is not explicitly disabled
            if (! $attr->isEditable() && $this->getShowOnlyEditableAttributes())
                continue;
            // Ignore attributes with fixed values
            if ($attr->getFixedValue())
                continue;
            // Create the widget
            $ed = $this->createWidgetFromAttribute($this->getMetaObject(), $attr->getAlias(), $parent_widget);
            if ($ed instanceof iCanBeRequired) {
                $ed->setRequired($attr->isRequired());
            }
            if ($ed instanceof iCanBeDisabled) {
                $ed->setDisabled(($attr->isEditable() ? false : true));
            }
            $editors[] = $ed;
        }
        
        if (count($editors) == 0){
            $editors[] = WidgetFactory::create($parent_widget->getPage(), 'Message', $parent_widget)
            ->setType(EXF_MESSAGE_TYPE_WARNING)
            ->setText($this->getApp()->getTranslator()->translate('ACTION.EDITOBJECTDIALOG.NO_EDITABLE_ATTRIBUTES'));
        }
        
        ksort($editors);
        
        return $editors;
    }

    function createWidgetFromAttribute($obj, $attribute_alias, $parent_widget)
    {
        $attr = $obj->getAttribute($attribute_alias);
        $page = $this->getCalledOnUiPage();
        $widget = WidgetFactory::createFromUxon($page, $attr->getDefaultWidgetUxon(), $parent_widget);
        $widget->setAttributeAlias($attribute_alias);
        $widget->setCaption($attr->getName());
        $widget->setHint($attr->getHint());
        return $widget;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Actions\ShowDialog::createDialogWidget()
     */
    protected function createDialogWidget(AbstractWidget $contained_widget = NULL)
    {
        $dialog = parent::createDialogWidget();
        $page = $this->getCalledOnUiPage();
        $default_editor_uxon = $dialog->getMetaObject()->getDefaultEditorUxon();
        
        // If there is a default editor, make sure it gets it's own id space, so widget links inside still work
        // if multiple editors of the same object are located in the same page (e.g. for creating, editing, etc.)
        if ($this->getCalledByWidget() && ! $default_editor_uxon->isEmpty()) {
            $default_editor_uxon->setProperty('id_space', $this->getCalledByWidget()->getId());
        }
        
        // If the content is explicitly defined, just add it to the dialog
        if ($contained_widget) {
            $dialog->addWidget($contained_widget);
        } // Otherwise try to generate the widget automatically
          // First check, if there is a default editor for an object, and instantiate it if so
        elseif ($default_editor_uxon && ! $default_editor_uxon->isEmpty()) {
            if (! $default_editor_uxon->getProperty('widget_type') || $default_editor_uxon->getProperty('widget_type') == 'Dialog') {
                $dialog->importUxonObject($default_editor_uxon);
                if ($dialog->countWidgets() == 0) {
                    $dialog->addWidgets($this->createWidgetsForAttributes($dialog));
                }
            } else {
                $default_editor = WidgetFactory::createFromUxon($page, $default_editor_uxon, $dialog);
                $dialog->addWidget($default_editor);
            }
        } // Lastly, try to generate a usefull editor from the meta model
else {
            // If there is no editor defined, create one: Add a panel to the dialog and generate editors for all attributes
            // of the object in that panel.
            // IDEA A separate method "create_object_editor" would probably be handy, once we have attribute groups and
            // other information, that would enable us to build better editors (with tabs, etc.)
            // FIXME Adding a form here is actually a workaround for wrong width calculation in the AdmnLTE template. It currently works only for forms there, not for panels.
            $panel = WidgetFactory::create($page, 'Form', $dialog);
            $panel->addWidgets($this->createWidgetsForAttributes($panel));
            $dialog->addWidget($panel);
        }
        return $dialog;
    }

    protected function enhanceDialogWidget(Dialog $dialog)
    {
        $dialog = parent::enhanceDialogWidget($dialog);
        if ($this->getDisableEditing()) {
            foreach ($dialog->getInputWidgets() as $widget) {
                $widget->setDisabled(true);
            }
        }
        return $dialog;
    }

    public function setDialogWidget(AbstractWidget $widget)
    {
        $this->dialog_widget = $widget;
    }

    /**
     * Returns TRUE if only widgets for editable attributes should be shown or FALSE, if all visible widgets should appear (some being disabled).
     *
     * @return boolean
     */
    public function getShowOnlyEditableAttributes()
    {
        return $this->show_only_editable_attributes;
    }

    public function setShowOnlyEditableAttributes($value)
    {
        $this->show_only_editable_attributes = $value;
        return $this;
    }

    public function getDisableEditing()
    {
        return $this->disable_editing;
    }

    public function setDisableEditing($value)
    {
        $this->disable_editing = BooleanDataType::parse($value);
        return $this;
    }
}
?>