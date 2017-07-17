<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iRunTemplateScript;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;

class CustomTemplateScript extends AbstractAction implements iRunTemplateScript
{

    private $script_language = "javascript";

    private $script = "";

    protected function init()
    {
        $this->setIconName(Icons::CODE);
    }

    protected function perform()
    {
        $this->setResultDataSheet($this->getInputDataSheet());
        $this->setResult($this->getScript());
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
     * @see \exface\Core\Interfaces\Actions\iRunTemplateScript::getScript()
     */
    public function getScript()
    {
        return $this->script;
    }

    public function setScript($value)
    {
        $this->script = $value;
    }

    /**
     *
     * @see \exface\Core\Interfaces\Actions\iRunTemplateScript::printScript()
     */
    public function printScript($widget_id)
    {
        return $this->prepareScript(array(
            "[#widget_id#]" => $widget_id
        ));
    }

    public function printHelperFunctions()
    {
        return '';
    }

    public function getIncludes()
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