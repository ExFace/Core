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
        $this->setIcon(Icons::PENCIL_MULTIPLE);
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
            $this->getWidget()->setCaption(intval($data_sheet->countRows()));
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
        // Add a message widget that displays what exactly we are editing here
        $counter_widget = WidgetFactory::create($this->getWidgetDefinedIn()->getPage(), 'Message', $dialog);
        $this->setAffectedCounterWidget($counter_widget);
        $counter_widget->setCaption('Affected objects');
        $dialog->addWidget($counter_widget, 0);
        // TODO Add a default save button that uses filter contexts
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
            if ($input_data->countRows()) {
                $counter = $input_data ? $input_data->countRows() : 0;
                return $this->translate('EDITING_SELECTED', array(
                    "%number%" => $counter
                ), $counter);
            } else {
                $filters = array();
                $filter_conditions = array_merge($input_data->getFilters()->getConditions(), $this->getApp()->getWorkbench()->getContext()->getScopeWindow()->getFilterContext()->getConditions($input_data->getMetaObject()));
                if (is_array($filter_conditions) && count($filter_conditions) > 0) {
                    foreach ($filter_conditions as $cond) {
                        $filters[$cond->getExpression()->toString()] = $cond->getExpression()->getAttribute()->getName() . ' (' . $cond->getExpression()->getAttribute()->getDataAddress() . ') ' . $cond->getComparator() . ' ' . $cond->getValue();
                    }
                    return $this->translate('EDITING_BY_FILTER', array(
                        '%filters%' => implode($filters, ' AND ')
                    ));
                } else {
                    return $this->translate('EDITING_ALL');
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