<?php
namespace exface\Core\Templates\AbstractTemplate;

use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Selectors\TemplateSelectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\CommonLogic\Traits\AliasTrait;

abstract class AbstractTemplate implements TemplateInterface
{
    use AliasTrait;

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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Templates\TemplateInterface::getSelector()
     */
    public function getSelector() : TemplateSelectorInterface
    {
        return $this->selector;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
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
            $this->app = $this->getWorkbench()->getApp($this->selector->getAppSelector());
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