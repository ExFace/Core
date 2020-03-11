<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Button;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\DataTypes\MessageTypeDataType;

class ShowObjectEditDialog extends ShowObjectInfoDialog
{

    private $save_action_alias = null;
    
    private $save_action_uxon = null;

    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::PENCIL_SQUARE_O);
        $this->setSaveActionAlias('exface.Core.UpdateData');
        $this->setShowOnlyEditableAttributes(true);
        $this->setDisableEditing(false);
        // Make sure, prefill with input data is enabled (otherwise there will be nothing to edit).
        $this->setPrefillWithInputData(true);
        // Disable prefills from context, so they do not interfere
        $this->setPrefillWithFilterContext(false);
    }

    /**
     * Create editors for all editable attributes of the object
     *
     * @return WidgetInterface[]
     */
    protected function createEditors(AbstractWidget $parent_widget)
    {
        $editors = [];
        if ($this->isObjectWritable() === false){
            $editors[] = WidgetFactory::create($parent_widget->getPage(), 'Message', $parent_widget)
            ->setType(MessageTypeDataType::WARNING)
            ->setWidth('100%')
            ->setText($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWOBJECTEDITDIALOG.DATA_SOURCE_NOT_WRITABLE'));
        }
        $editors = array_merge($editors, parent::createWidgetsForAttributes($parent_widget));
        return $editors;
    }

    /**
     * Creates the dialog widget.
     * Just the dialog itself, no contents!
     *
     * @return WidgetInterface
     */
    protected function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = NULL)
    {
        $dialog = parent::createDialogWidget($page, $contained_widget);
        /* @var $save_button \exface\Core\Widgets\Button */
        $save_button = $dialog->createButton(new UxonObject(['action' => $this->getSaveActionUxon()]));
        $save_button
            ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate("ACTION.SHOWOBJECTEDITDIALOG.SAVE_BUTTON"))
            ->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED)
            ->setAlign(EXF_ALIGN_OPPOSITE);
        // Make the save button refresh the same widget as the Button showing the dialog would do
        if ($this->getWidgetDefinedIn() instanceof Button) {
            $save_button->setRefreshWidgetIds($this->getWidgetDefinedIn()->getRefreshWidgetIds(false));
            $save_button->setResetWidgetIds($this->getWidgetDefinedIn()->getResetWidgetIds(false));
            $this->getWidgetDefinedIn()->setRefreshWidgetLink(null);
        }
        $dialog->addButton($save_button);
        $dialog->getCloseButton()->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWDIALOG.CANCEL_BUTTON'));
        return $dialog;
    }

    public function setDialogWidget(AbstractWidget $widget)
    {
        $this->dialog_widget = $widget;
    }

    /**
     * @deprecated use setSaveAction() instead
     * 
     * @param string $value
     * @return ShowObjectEditDialog
     */
    public function setSaveActionAlias(string $value) : ShowObjectEditDialog
    {
        $this->save_action_alias = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getSaveActionAlias() : string
    {
        return $this->save_action_alias;
    }
    
    /**
     * Defines a custom action for the save-button of the dialog.
     * 
     * @uxon-property save_action
     * @uxon-type \exface\Core\Actions\SaveData
     * 
     * @param UxonObject $uxon
     * @return ShowObjectEditDialog
     */
    public function setSaveAction(UxonObject $uxon) : ShowObjectEditDialog
    {
        $this->save_action_uxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getSaveActionUxon() : UxonObject
    {
        if ($this->save_action_uxon === null) {
            $this->save_action_uxon = new UxonObject();
        }
        
        if ($this->save_action_uxon->hasProperty('alias') === false) {
            $this->save_action_uxon->setProperty('alias', $this->getSaveActionAlias());
        }
        
        return $this->save_action_uxon;
    }
}
?>