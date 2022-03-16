<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\Dialog;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Button;
use exface\Core\CommonLogic\UxonObject;

/**
 * Shows a dialog for mass editing based on selection or filters.
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowMassEditDialog extends ShowDialog
{

    private $affected_counter_widget = NULL;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        $this->setInputRowsMin(null);
        $this->setInputRowsMax(null);
        $this->setMaximize(false);
        $this->setIcon(Icons::LIST_);
        $this->setPrefillWithInputData(true);
        $this->setPrefillWithFilterContext(false);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        if ($this->isWidgetDefined()) {
            if ($counter = $this->getAffectedCounterWidget()) {
                $counter->setText($this->getAffectedCounterText($data_sheet));
            }
        }
        return parent::perform($task, $transaction);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowDialog::enhanceDialogWidget()
     */
    protected function enhanceDialogWidget(Dialog $dialog)
    {
        if ($dialog->countWidgetsVisible() === 1) {
            $dialog->setHeight('auto');
            $dialog->setWidth(1);
        }
        
        // Add a message widget that displays what exactly we are editing here
        $counter_widget = WidgetFactory::create($this->getWidgetDefinedIn()->getPage(), 'Message', $dialog);
        $this->setAffectedCounterWidget($counter_widget);
        $dialog->addWidget($counter_widget, 0);
        
        // Add a default save button that uses filter contexts
        // TODO make this button configurable via UXON
        $save_button = $dialog->createButton(new UxonObject([
            'action_alias' => 'exface.Core.MassUpdateData',
            'visibility' => EXF_WIDGET_VISIBILITY_PROMOTED,
            'align' => EXF_ALIGN_OPPOSITE,
            'caption' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate("ACTION.SHOWOBJECTEDITDIALOG.SAVE_BUTTON")
        ]));
        
        // Make the save button refresh the same widget as the Button showing the dialog would do
        if ($this->getWidgetDefinedIn() instanceof Button) {
            $save_button->setRefreshWidgetIds($this->getWidgetDefinedIn()->getRefreshWidgetIds(false));
            $save_button->setResetWidgetIds($this->getWidgetDefinedIn()->getResetWidgetIds(false));
            $this->getWidgetDefinedIn()->setRefreshWidgetLink(null);
        }
        $dialog->addButton($save_button);
        
        return parent::enhanceDialogWidget($dialog);
    }

    /**
     * 
     * @param DataSheetInterface $input_data
     * @return string
     */
    protected function getAffectedCounterText(DataSheetInterface $input_data)
    {
        if ($input_data) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            if ($input_data->countRows()) {
                $counter = $input_data ? $input_data->countRows() : 0;
                return $translator->translate('ACTION.SHOWMASSEDITDIALOG.EDITING_SELECTED', array(
                    "%number%" => $counter
                ), $counter);
            } else {
                $filters = array();
                $filter_conditions = array_merge($input_data->getFilters()->getConditions(), $this->getApp()->getWorkbench()->getContext()->getScopeWindow()->getFilterContext()->getConditions($input_data->getMetaObject()));
                if (is_array($filter_conditions) && count($filter_conditions) > 0) {
                    foreach ($filter_conditions as $cond) {
                        $filters[$cond->getExpression()->toString()] = $cond->getExpression()->getAttribute()->getName() . ' ' . $cond->getComparator() . ' ' . $cond->getValue();
                    }
                    return $translator->translate('ACTION.SHOWMASSEDITDIALOG.EDITING_BY_FILTER', [
                        '%filters%' => implode(' AND ', $filters)
                    ]);
                } else {
                    return $translator->translate('ACTION.SHOWMASSEDITDIALOG.EDITING_ALL');
                }
            }
        }
    }

    /**
     * 
     * @return \exface\Core\Interfaces\WidgetInterface|null
     */
    protected function getAffectedCounterWidget()
    {
        return $this->affected_counter_widget;
    }

    /**
     * 
     * @param WidgetInterface $widget
     * @return \exface\Core\Actions\ShowMassEditDialog
     */
    protected function setAffectedCounterWidget(WidgetInterface $widget)
    {
        $this->affected_counter_widget = $widget;
        return $this;
    }
}
?>