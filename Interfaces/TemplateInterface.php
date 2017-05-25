<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\Configuration;

interface TemplateInterface extends ExfaceClassInterface, AliasInterface
{

    function draw(\exface\Core\Widgets\AbstractWidget $widget);

    /**
     * Generates the declaration of the JavaScript sources
     *
     * @return string
     */
    function drawHeaders(\exface\Core\Widgets\AbstractWidget $widget);

    /**
     *
     * @return string
     */
    public function getAlias();

    /**
     * Processes the current HTTP request, assuming it was made from a UI using this template
     *
     * @return string
     */
    public function processRequest();

    /**
     * Returns TRUE if this template matches the given template alias and false otherwise (case insensitive!)
     *
     * @param string $template_alias            
     */
    public function is($template_alias);

    /**
     *
     * @return string
     */
    public function getResponse();

    /**
     *
     * @param string $value            
     * @return \exface\Core\Interfaces\TemplateInterface
     */
    public function setResponse($value);

    /**
     * Returns the app, that contains the template
     *
     * @return AppInterface
     */
    public function getApp();

    /**
     * Returns the configuration object for this template.
     * By default, it is the configuration object of the app of the template
     *
     * @return Configuration
     */
    public function getConfig();
}
?>