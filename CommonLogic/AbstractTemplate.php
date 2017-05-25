<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Factories\AppFactory;

abstract class AbstractTemplate implements TemplateInterface
{

    private $exface = null;

    private $app = null;

    private $alias = '';

    private $name_resolver = null;

    private $response = '';

    public final function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
        $this->alias = substr(get_class($this), (strrpos(get_class($this), DIRECTORY_SEPARATOR) + 1));
        $this->init();
    }

    protected function init()
    {}

    /**
     *
     * @return \exface\Core\Interfaces\NameResolverInterfacer
     */
    public function getNameResolver()
    {
        if (is_null($this->name_resolver)) {
            $this->name_resolver = NameResolver::createFromString(get_class($this), NameResolver::OBJECT_TYPE_TEMPLATE, $this->exface);
        }
        return $this->name_resolver;
    }

    /**
     *
     * @param NameResolverInterface $value            
     * @return \exface\Core\CommonLogic\AbstractTemplate
     */
    public function setNameResolver(NameResolverInterface $value)
    {
        $this->name_resolver = $value;
        return $this;
    }

    public function getNamespace()
    {
        return $this->getNameResolver()->getNamespace();
    }

    public function getAliasWithNamespace()
    {
        return $this->getNameResolver()->getAliasWithNamespace();
    }

    public function getAlias()
    {
        return $this->alias;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     * @return Workbench
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    abstract function draw(\exface\Core\Widgets\AbstractWidget $widget);

    /**
     * Generates the declaration of the JavaScript sources
     *
     * @return string
     */
    abstract function drawHeaders(\exface\Core\Widgets\AbstractWidget $widget);

    /**
     * Processes the current HTTP request, assuming it was made from a UI using this template
     *
     * @param string $page_id            
     * @param string $widget_id            
     * @param string $action_alias            
     * @param boolean $disable_error_handling            
     * @return string
     */
    abstract function processRequest($page_id = NULL, $widget_id = NULL, $action_alias = NULL, $disable_error_handling = false);

    public function is($template_alias)
    {
        if (strcasecmp($this->getAlias(), $template_alias) === 0 || strcasecmp($this->getAliasWithNamespace(), $template_alias) === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TemplateInterface::getResponse()
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TemplateInterface::setResponse()
     */
    public function setResponse($value)
    {
        $this->response = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TemplateInterface::getApp()
     */
    public function getApp()
    {
        if (is_null($this->app)) {
            $this->app = AppFactory::createFromAlias($this->getNameResolver()->getAliasWithNamespace(), $this->exface);
        }
        return $this->app;
    }

    public function setApp(AppInterface $value)
    {
        $this->app = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TemplateInterface::getConfig()
     */
    public function getConfig()
    {
        return $this->getApp()->getConfig();
    }
}
?>