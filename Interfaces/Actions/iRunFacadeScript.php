<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\Facades\FacadeInterface;

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
     * Returns valid java script, that executes the action.
     * The parameter $element_id contains the
     * java script id of the element the script should be applied to (in general the input widget of the
     * action).
     * In the result of this method placeholders are already replaced! How exactly this is achieved
     * and what else happens to the script is subject of the specific implementation.
     *
     * @param string $element_id            
     * @return string valid java script
     */
    public function buildScript($element_id);

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