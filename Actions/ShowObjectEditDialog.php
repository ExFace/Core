<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Button;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Model\UiPageInterface;

class ShowObjectEditDialog extends ShowObjectInfoDialog
{

    private $save_action_alias = null;

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
        return parent::createWidgetsForAttributes($parent_widget);
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
        // TODO add save button via followup actions in the init() method instead of the button directly
        /* @var $save_button \exface\Core\Widgets\Button */
        $save_button = $dialog->createButton();
        $save_button
            ->setActionAlias($this->getSaveActionAlias())
            ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate("ACTION.SHOWOBJECTEDITDIALOG.SAVE_BUTTON"))
            ->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED)
            ->setAlign(EXF_ALIGN_OPPOSITE);
        // Make the save button refresh the same widget as the Button showing the dialog would do
        if ($this->getWidgetDefinedIn() instanceof Button) {
            $save_button->setRefreshWidgetLink($this->getWidgetDefinedIn()->getRefreshWidgetLink());
            $this->getWidgetDefinedIn()->setRefreshWidgetLink(null);
        }
        $dialog->addButton($save_button);
        return $dialog;
    }

    public function setDialogWidget(AbstractWidget $widget)
    {
        $this->dialog_widget = $widget;
    }

    public function getSaveActionAlias()
    {
        return $this->save_action_alias;
    }

    public function setSaveActionAlias($value)
    {
        $this->save_action_alias = $value;
    }
}
?>