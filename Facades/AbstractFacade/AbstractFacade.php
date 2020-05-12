<?php
namespace exface\Core\Facades\AbstractFacade;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\CommonLogic\Traits\AliasTrait;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Uxon\FacadeSchema;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Events\Facades\OnFacadeInitEvent;

abstract class AbstractFacade implements FacadeInterface
{
    use AliasTrait;
    
    use ImportUxonObjectTrait;

    private $exface = null;

    private $app = null;

    private $selector = null;

    public final function __construct(FacadeSelectorInterface $selector)
    {
        $this->exface = $selector->getWorkbench();
        $this->selector = $selector;
        $this->init();
        
        $this->exface->eventManager()->dispatch(new OnFacadeInitEvent($this));
    }

    protected function init()
    {}

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
    public function is($aliasOrSelector) : bool
    {
        if ($aliasOrSelector instanceof FacadeSelectorInterface) {
            if ($aliasOrSelector->isAlias()) {
                $facade_alias = $aliasOrSelector->toString();
            } else {
                // TODO add support for other selectors
                throw new NotImplementedError('Cannot compare facade "' . $this->getAliasWithNamespace() . '" with selector "' . $aliasOrSelector->toString() . '": currently only alias-selectors supported!');
            }
        }
        // TODO check if this facade is a derivative of the facade matching the selector
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
        if (is_null($this->app)) {
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject();
    }
    
    /**
     * 
     * @return string|NULL
     */
    public static function getUxonSchemaClass() : ?string
    {
        return FacadeSchema::class;
    }
}
?>