<?php
namespace exface\Core\Facades\ConsoleFacade;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\CommonLogic\Traits\AliasTrait;
use Symfony\Component\Console\Application;

/**
 * Command line interface facade based on Symfony Console
 * 
 * @author Andrej Kabachnik
 *
 */
class ConsoleFacade extends Application implements FacadeInterface
{
    use AliasTrait;

    private $exface = null;

    private $app = null;

    private $selector = null;

    public final function __construct(FacadeSelectorInterface $selector)
    {
        parent::__construct('ExFace Console');
        $this->exface = $selector->getWorkbench();
        $this->selector = $selector;
        $this->setCommandLoader(new CommandLoader($this));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::getSelector()
     */
    public function getSelector() : FacadeSelectorInterface
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
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::is()
     */
    public function is($facade_alias) : bool
    {
        if (strcasecmp($this->getAlias(), $facade_alias) === 0 || strcasecmp($this->getAliasWithNamespace(), $facade_alias) === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::getApp()
     */
    public function getApp() : AppInterface
    {
        if ($this->app === null) {
            $this->app = $this->getWorkbench()->getApp($this->selector->getAppSelector());
        }
        return $this->app;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::getConfig()
     */
    public function getConfig() : ConfigurationInterface
    {
        return $this->getApp()->getConfig();
    }
}