<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\Dialog;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * Open a dialog to perform an advanced search for values for a specified input widget.
 *
 * This action is very usefull for all sorts of select widgets. Although these often have internal search or autosuggest funcionality,
 * it is often nessecary to search for a value using multiple filters, sorting, etc. This action will open a dialog with any search
 * widget you like (the default table widget of the target object by default) and put the selected value into the target input or select
 * widget once the dialog is closed.
 *
 * ## Example
 * 
 * ```
 *  {
 *      "widget_type": "Form",
 *      "object_alias" "my.app.ORDER"
 *      "widgets": [
 *          {
 *              "widget_type": "InputComboTable",
 *              "attribute_alias": "CUSTOMER",
 *              "id": "customer_selector"
 *          }
 *      ],
 *      "buttons": [
 *          {
 *              "action":
 *                  {
 *                      "alias": "exface.Core.ShowLookupDialog",
 *                      "object_alias": "my.app.CUSTOMER",
 *                      "target_widget_id": "customer_selector"
 *                  }
 *          }
 *      ]
 *  }
 *  
 * ````
 *
 * This action can be used with any widget, that accepts input.
 *
 * @author Stefan Leupold
 * @author Thomas Michael
 *        
 */
class ShowLookupDialog extends ShowDialog
{
    private $target_widget_id = null;
    
    private $multi_select = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setPrefillWithInputData(false);
        $this->setIcon(Icons::SEARCH);
        
        if ($this->isDefinedInWidget() === true && $this->getWidgetDefinedIn()->is('DialogButton')) {
            $this->getWidgetDefinedIn()->setCloseDialogAfterActionSucceeds(false);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowDialog::getDialogWidgetType()
     */
    protected function getDialogWidgetType() : string
    {
        return 'DataLookupDialog';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowDialog::enhanceDialogWidget()
     */
    protected function enhanceDialogWidget(Dialog $dialog)
    {
        $dialog = parent::enhanceDialogWidget($dialog);
        
        if ($this->getMultiSelect() !== null) {
            $dialog->setMultiSelect($this->getMultiSelect());
        }
        
        /* @var $data_table \exface\Core\Widgets\DataTable */
        $data_table = $dialog->getDataWidget();
        
        if ($data_table->getMetaObject()->hasLabelAttribute() === true) {
            $labelAlias = $data_table->getMetaObject()->getLabelAttributeAlias();
            if (! $data_table->getColumnByAttributeAlias($labelAlias) && ! $data_table->getColumnByDataColumnName($this->getWorkbench()->getConfig()->getOption("METAMODEL.OBJECT_LABEL_ALIAS"))) {
                $data_table->addColumn($data_table->createColumnFromAttribute($data_table->getMetaObject()->getLabelAttribute()));
            }
        }
        
        // @var $save_button \exface\Core\Widgets\Button
        $save_button = $dialog->createButton(new UxonObject([
            'caption' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate("ACTION.SHOWLOOKUPDIALOG.SAVE_BUTTON"),
            'visibility' => WidgetVisibilityDataType::PROMOTED,
            'input_widget_id' => $data_table->getId(),
            'action' => [
                'alias' => 'exface.Core.SendToWidget',
                'target_widget_id' => $this->getTargetWidgetId()
            ]
        ]));
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
     * The id of the widget to receive the selected values.
     *
     * @uxon-property target_widget_id
     * @uxon-type uxon:$..id
     *
     * @param boolean $value            
     * @return \exface\Core\Actions\ShowLookupDialog
     */
    public function setTargetWidgetId($value)
    {
        $this->target_widget_id = $value;
        return $this;
    }
    
    /**
     * Set to TRUE to allow selection of multiple entries in the lookup dialog.
     * 
     * If the lookup dialog is called from an input widget (e.g. `InputComboTable`) this setting
     * is inherited from that input. Otherwise it is `false` by default.
     * 
     * @uxon-property multi_select
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return ShowLookupDialog
     */
    public function setMultiSelect(bool $trueOrFalse) : ShowLookupDialog
    {
        $this->multi_select = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool|NULL
     */
    protected function getMultiSelect() : ?bool
    {
        return $this->multi_select;
    }
}