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
    public function buildScript($widget_id)
    {
        return $this->prepareScript(array(
            "[#widget_id#]" => $widget_id
        ));
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

    /**
     * Replaces placeholders in the script, thus preparing it for use.
     * Expects a placeholders array of the
     * form [placeholder => value]. If the script is not passed directly, getScript() will be used to get it.
     * This method can be overridden to easiliy extend or modify the script specified in UXON.
     *
     * @param array $placeholders
     *            [placeholder => value]
     * @param string $script            
     * @return string valid java script
     */
    protected function prepareScript(array $placeholders, $script = null)
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), ($script ? $script : $this->getScript()));
    }
}
?>