<?php
namespace exface\Core\Interfaces\Api;

use exface\Core\CommonLogic\Configuration;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\WidgetInterface;

interface TemplateInterface extends ExfaceClassInterface, AliasInterface
{

    public function buildWidget(WidgetInterface $widget);

    /**
     *
     * @return string
     */
    public function getAlias();

    /**
     * Returns TRUE if this template matches the given template alias and false otherwise (case insensitive!)
     *
     * @param string $template_alias            
     */
    public function is($template_alias);

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