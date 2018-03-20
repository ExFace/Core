<?php
namespace exface\Core\Templates\AbstractTemplate;

use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Factories\AppFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Selectors\TemplateSelectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;

abstract class AbstractTemplate implements TemplateInterface
{

    private $exface = null;

    private $app = null;

    private $selector = null;

    public final function __construct(TemplateSelectorInterface $selector)
    {
        $this->exface = $selector->getWorkbench();
        $this->selector = $selector;
        $this->init();
    }

    protected function init()
    {}

    public function getSelector() : TemplateSelectorInterface
    {
        return $this->selector;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return $this->selector->getNamespace();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->selector->getAliasWithNamespace();
    }

    public function getAlias()
    {
        return $this->selector->getAlias();
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Templates\TemplateInterface::is()
     */
    public function is($template_alias) : bool
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
     * @see \exface\Core\Interfaces\Templates\TemplateInterface::getApp()
     */
    public function getApp() : AppInterface
    {
        if (is_null($this->app)) {
            $this->app = AppFactory::createFromAlias($this->selector->getNamespace(), $this->exface);
        }
        return $this->app;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Templates\TemplateInterface::getConfig()
     */
    public function getConfig() : ConfigurationInterface
    {
        return $this->getApp()->getConfig();
    }
}
?>