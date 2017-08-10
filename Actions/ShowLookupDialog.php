<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\Dialog;

/**
 * Open a dialog to perform an advanced search for values for a specified input widget.
 *
 * This action is very usefull for all sorts of select widgets. Although these often have internal search or autosuggest funcionality,
 * it is often nessecary to search for a value using multiple filters, sorting, etc. This action will open a dialog with any search
 * widget you like (the default table widget of the target object by default) and put the selected value into the target input or select
 * widget once the dialog is closed.
 *
 * Basic Example:
 * {
 * "widget_type": "Form",
 * "object_alias" "my.app.ORDER"
 * "widgets": [
 * {
 * "widget_type": "ComboTable",
 * "attribute_alias": "CUSTOMER",
 * "id": "customer_selector"
 * }
 * ],
 * "buttons": [
 * {
 * "action":
 * {
 * "alias": "exface.Core.ShowLookupDialog",
 * "object_alias": "my.app.CUSTOMER",
 * "target_widget_id": "customer_selector"
 * }
 * }
 * ]
 * }
 *
 * This action can be used with any widget, that accepts input.
 *
 * @author Stefan Leupold
 *        
 */
class ShowLookupDialog extends ShowDialog
{

    private $target_widget_id = null;

    protected function init()
    {
        parent::init();
        $this->setPrefillWithInputData(false);
        
        if ($this->getCalledByWidget() && $this->getCalledByWidget()->is('DialogButton')) {
            $this->getCalledByWidget()->setCloseDialogAfterActionSucceeds(false);
        }
    }

    protected function enhanceDialogWidget(Dialog $dialog)
    {
        $dialog = parent::enhanceDialogWidget($dialog);
        $page = $this->getCalledOnUiPage();
        
        if ($dialog->isEmpty()) {
            $data_table = WidgetFactory::create($page, 'DataTable', $dialog);
            $data_table->setMetaObject($this->getMetaObject());
            $dialog->addWidget($data_table);
        } else {
            $data_table = reset($dialog->getWidgets());
        }
        
        // @var $save_button \exface\Core\Widgets\Button
        $save_button = $dialog->createButton();
        $save_button->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate("ACTION.SHOWLOOKUPDIALOG.SAVE_BUTTON"));
        $save_button->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED);
        
        // @var $save_action \exface\Core\Actions\CustomTemplateScript
        $save_action = ActionFactory::createFromString($this->getWorkbench(), 'exface.Core.CustomTemplateScript', $save_button);
        $source_element = $this->getTemplate()->getElement($data_table);
        $target_element = $this->getTemplate()->getElementByWidgetId($this->getTargetWidgetId(), $page->getId());
        $save_action_script = $target_element->buildJsValueSetter($source_element->buildJsValueGetter());
        $save_action->setScript($save_action_script);
        
        $save_button->setAction($save_action);
        $dialog->addButton($save_button);
        
        return $dialog;
    }

    /**
     *
     * @return boolean
     */
    public function getTargetWidgetId()
    {
        return $this->target_widget_id;
    }

    /**
     * The widget which should receive the selected values.
     *
     * @uxon-property target_widget_id
     * @uxon-type string
     *
     * @param boolean $value            
     * @return \exface\Core\Actions\ShowLookupDialog
     */
    public function setTargetWidgetId($value)
    {
        $this->target_widget_id = $value;
        return $this;
    }
}

?>
