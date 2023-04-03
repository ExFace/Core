<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * A special binding model for storing bindings resulting from prefilling a widgets
 * 
 * Prefill binding models are capable of generating their bindings automatically from actions or tasks.
 * 
 * @author Andrej Kabachnik
 *
 */
interface PrefillModelInterface extends BindingModelInterface
{    
    /**
     *
     * @param TaskInterface $task
     * @return PrefillModelInterface
     */
    public function addBindingsFromTask(TaskInterface $task) : PrefillModelInterface;
    
    /**
     * 
     * @param iPrefillWidget $action
     * @return PrefillModelInterface
     */
    public function addBindingsFromAction(iPrefillWidget $action) : PrefillModelInterface;
    
    /**
     * 
     * @param WidgetInterface $inputWidget
     * @return DataSheetInterface|NULL
     */
    public function getExpectedInputData(WidgetInterface $inputWidget = null) : ?DataSheetInterface;
    
    /**
     * 
     * @param WidgetInterface $inputWidget
     * @return DataSheetInterface|NULL
     */
    public function getExpectedPrefillData(WidgetInterface $inputWidget = null) : ?DataSheetInterface;
}