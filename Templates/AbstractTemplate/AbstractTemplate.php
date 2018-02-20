<?php
namespace exface\Core\Templates\AbstractTemplate;

use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Factories\AppFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\NameResolver;

abstract class AbstractTemplate implements TemplateInterface
{

    private $exface = null;

    private $app = null;

    private $alias = '';

    private $name_resolver = null;

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
     * @return NameResolverInterface
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
     * @return \exface\Core\Templates\AbstractTemplate\AbstractTemplate
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
     * @see \exface\Core\Interfaces\Templates\TemplateInterface::getApp()
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
     * @see \exface\Core\Interfaces\Templates\TemplateInterface::getConfig()
     */
    public function getConfig()
    {
        return $this->getApp()->getConfig();
    }
}
?>