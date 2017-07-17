<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\Dialog;
use exface\Core\CommonLogic\Constants\Icons;

class MassEditDialog extends ShowDialog
{

    private $affected_counter_widget_id = NULL;

    protected function init()
    {
        $this->setInputRowsMin(null);
        $this->setInputRowsMax(null);
        $this->setIconName(Icons::PENCIL_MULTIPLE);
        $this->setPrefillWithInputData(true);
        $this->setPrefillWithFilterContext(false);
    }

    public function setInputDataSheet($data_sheet)
    {
        $result = parent::setInputDataSheet($data_sheet);
        $data_sheet = $this->getInputDataSheet();
        if ($this->getWidget()) {
            $this->getWidget()->setCaption(intval($data_sheet->countRows()));
            if ($counter = $this->getCalledOnUiPage()->getWidget($this->getAffectedCounterWidgetId(), $this->getWidget())) {
                $counter->setText($this->getAffectedCounterText());
            }
        }
        return $result;
    }

    protected function enhanceDialogWidget(Dialog $dialog)
    {
        // Add a message widget that displays what exactly we are editing here
        $counter_widget = $this->getCalledOnUiPage()->createWidget('Message', $dialog);
        $this->setAffectedCounterWidgetId($counter_widget->getId());
        $counter_widget->setCaption('Affected objects');
        $counter_widget->setText($this->getAffectedCounterText());
        $dialog->addWidget($counter_widget, 0);
        // TODO Add a default save button that uses filter contexts
        return parent::enhanceDialogWidget($dialog);
    }

    protected function getAffectedCounterText()
    {
        if ($this->getInputDataSheet()) {
            if ($this->getInputDataSheet()->countRows()) {
                $counter = $this->getInputDataSheet() ? $this->getInputDataSheet()->countRows() : 0;
                return $this->translate('EDITING_SELECTED', array(
                    "%number%" => $counter
                ), $counter);
            } else {
                $filters = array();
                $filter_conditions = array_merge($this->getInputDataSheet()->getFilters()->getConditions(), $this->getApp()->getWorkbench()->context()->getScopeWindow()->getFilterContext()->getConditions($this->getInputDataSheet()->getMetaObject()));
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

    public function getAffectedCounterWidgetId()
    {
        return $this->affected_counter_widget_id;
    }

    public function setAffectedCounterWidgetId($value)
    {
        $this->affected_counter_widget_id = $value;
        return $this;
    }
}
?>