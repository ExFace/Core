<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iRunFacadeScript;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Runs a custom script (e.g. JavaScript) if supported by the current facade
 * 
 * Which scripts and lanuages are supported depends on the facade used. Most JavaScript based
 * facades support custom JavaScript.
 * 
 * ## Available placeholders
 * 
 * - `[#element_id:~self#]` - the id of the facade element triggering the script - e.g. the DOM
 * element id of a button. Example use: `$('#[#element_id:~self#]').hide()`.
 * - `[#element_id:~parent#]` - the id of the facade element of the parent widget
 * - `[#element_id:~input#]` - the id of the facade element of the input widget - e.g. the DOM
 * element id of a table. Example use: `$('#[#element_id:~input#]')`.
 * 
 * @author andrej.kabachnik
 *
 */
class CustomFacadeScript extends AbstractAction implements iRunFacadeScript
{

    private $script_language = "javascript";

    private $script = "";

    protected function init()
    {
        $this->setIcon(Icons::CODE);
    }

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        return ResultFactory::createTextContentResult($task, $this->getScript());
    }

    public function getScriptLanguage()
    {
        return $this->script_language;
    }

    public function setScriptLanguage($value)
    {
        $this->script_language = $value;
    }

    /**
     *
     * @see \exface\Core\Interfaces\Actions\iRunFacadeScript::getScript()
     */
    public function getScript()
    {
        return $this->script;
    }

    /**
     * Script to run when action is called.
     * 
     * ## Available placeholders
     * 
     * - `[#element_id:~self#]` - the id of the facade element triggering the script - e.g. the DOM
     * element id of a button. Example use: `$('#[#element_id:~self#]').hide()`.
     * - `[#element_id:~parent#]` - the id of the facade element of the parent widget
     * - `[#element_id:~input#]` - the id of the facade element of the input widget - e.g. the DOM
     * element id of a table. Example use: `$('#[#element_id:~input#]')`.
     * 
     * @uxon-property script
     * @uxon-type string
     * 
     * @param string $value
     */
    public function setScript($value)
    {
        $this->script = $value;
        return $this;
    }

    /**
     *
     * @see \exface\Core\Interfaces\Actions\iRunFacadeScript::buildScript()
     */
    public function buildScript(FacadeInterface $facade, WidgetInterface $widget)
    {
        return $this->getScript();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iRunFacadeScript::buildScriptHelperFunctions()
     */
    public function buildScriptHelperFunctions(FacadeInterface $facade) : string
    {
        return '';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iRunFacadeScript::getIncludes()
     */
    public function getIncludes(FacadeInterface $facade) : array
    {
        return array();
    }
}
?>