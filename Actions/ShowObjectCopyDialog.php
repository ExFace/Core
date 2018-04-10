<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Renders a dialog to create a copy of the input object.
 * 
 * Dialog rendering works just like in ShowObjectEditDialog, but the save-button creates a copy
 * instead of modifying the given object.
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowObjectCopyDialog extends ShowObjectEditDialog
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowObjectEditDialog::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::CLONE_);
        $this->setSaveActionAlias('exface.Core.CreateData');
    }

    /**
     * In the case of the dublicate-action we need to remove the UID column from the data sheet to ensure, that the
     * duplicated object will get new ids.
     *
     * {@inheritdoc} 
     * @see \exface\Core\Actions\ShowWidget::prefillWidget()
     */
    protected function prefillWidget(TaskInterface $task, WidgetInterface $widget) : WidgetInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        
        if ($data_sheet->getUidColumn()) {
            $data_sheet = $this->getWidget()->prepareDataSheetToPrefill($data_sheet);
            if (! $data_sheet->isFresh()) {
                $data_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
                $data_sheet->dataRead();
            }
            $data_sheet->getColumns()->removeByKey($data_sheet->getUidColumn()->getName());
        }
        
        $widget->prefill($data_sheet);
        
        return $widget;
    }
}
?>