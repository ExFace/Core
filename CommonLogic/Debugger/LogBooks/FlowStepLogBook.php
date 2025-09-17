<?php

namespace exface\Core\CommonLogic\Debugger\LogBooks;

use axenox\ETL\Interfaces\ETLStepDataInterface;
use axenox\ETL\Interfaces\ETLStepInterface;
use exface\Core\DataTypes\PhpClassDataType;

/**
 * Logging class for ETLSteps.
 */
class FlowStepLogBook extends DataLogBook
{
    private ETLStepInterface $step;
    private ETLStepDataInterface $stepData;

    /**
     * @param string               $title
     * @param ETLStepInterface     $step
     * @param ETLStepDataInterface $stepData
     */
    public function __construct(string $title, ETLStepInterface $step, ETLStepDataInterface $stepData)
    {
        parent::__construct($title);
        $this->step = $step;
        $this->stepData = $stepData;

        $this->addSection($step->getName());
        $this->addIndent(1);

        $this->addLine(PhpClassDataType::findClassNameWithoutNamespace($step) . 
            ' from ' . $step->getFromObject()->__toString() . 
            ' to ' . $step->getToObject()->__toString());
        
        $task = $stepData->getTask();
        if ($task->isTriggeredByWidget()) {
            try {
                $trigger = $task->getWidgetTriggeredBy();
                $this->addLine('Trigger widget: ' . $trigger->getWidgetType() . ' "**' . $trigger->getCaption() . '**"');
            } catch (\Throwable $e) {
                $this->addLine('Trigger widget not accessible: ' . $e->getMessage());
            }
        } else {
            $this->addLine('Trigger widget not known');
        }
        
        $this->addIndent(-1);
    }

    /**
     * @return ETLStepInterface
     */
    public function getStep() : ETLStepInterface
    {
        return $this->step;
    }

    /**
     * @return ETLStepDataInterface
     */
    public function getStepData() : ETLStepDataInterface
    {
        return $this->stepData;
    }
}