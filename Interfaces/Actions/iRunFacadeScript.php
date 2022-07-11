<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;

interface iRunFacadeScript extends ActionInterface
{

    /**
     * Returns the script language
     */
    public function getScriptLanguage();

    /**
     * Returns the script exactly the way it was specified - without any postprocessing.
     * All placeholders are still there.
     */
    public function getScript();

    /**
     * Returns an array of include paths relative to the facades js-folder
     *
     * @return array()
     */
    public function getIncludes(FacadeInterface $facade) : array;

    /**
     * Returns java script, that executes the action.
     * The parameter $facade contains the action is called from and $widget contains the
     * input widget of the action.
     * What exactly happens to the script (for example replacing placeholders or not) is
     * subject of the specific implementation.
     *
     * @param FacadeInterface $facade
     * @param WidgetInterface $widget
     */
    public function buildScript(FacadeInterface $facade, WidgetInterface $widget);

    /**
     * Returns java script code, that needs to be placed outside the actions script.
     * Typically this will
     * be some functions used in the script, global variables, etc.
     * The parameter $element_id contains the
     * java script id of the element the script should be applied to (in general the input widget of the
     * action).
     *
     * @param string $element_id            
     * @return string valid java script
     */
    public function buildScriptHelperFunctions(FacadeInterface $facade) : string;
}
?>