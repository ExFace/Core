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

    public function getIncludes(FacadeInterface $facade) : array
    {
        return array();
    }
}
?>