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
use exface\Core\CommonLogic\Selectors\FacadeSelector;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;

abstract class AbstractFacade implements FacadeInterface
{
    use AliasTrait;
    
    use ImportUxonObjectTrait;

    private $exface = null;

    private $app = null;

    private $selector = null;

    public function __construct(FacadeSelectorInterface $selector)
    {
        $this->exface = $selector->getWorkbench();
        $this->selector = $selector;
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
    public function is($aliasOrSelector) : bool
    {
        $selector = $aliasOrSelector instanceof FacadeSelectorInterface ? $aliasOrSelector : new FacadeSelector($this->getWorkbench(), $aliasOrSelector);
        if ($this->isExactly($selector)) {
            return true;
        }
        switch (true) {
            case $aliasOrSelector->isAlias():
                $aliasOrSelector = $aliasOrSelector->toString();
                // TODO check if this facade is a derivative of the facade matching the selector
                if (strcasecmp($this->getAlias(), $aliasOrSelector) === 0 || strcasecmp($this->getAliasWithNamespace(), $aliasOrSelector) === 0) {
                    return true;
                } else {
                    return false;
                }
            default:
                // TODO add support for other selectors
                
        }
        throw new NotImplementedError('Cannot compare facade "' . $this->getAliasWithNamespace() . '" with selector "' . $aliasOrSelector->toString() . '": currently only alias-selectors supported!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\FacadeInterface::isExactly()
     */
    public function isExactly($selectorOrString) : bool
    {
        $selector = $selectorOrString instanceof FacadeSelectorInterface ? $selectorOrString : new FacadeSelector($this->getWorkbench(), $selectorOrString);
        switch(true) {
            case $selector->isFilepath():
                $selectorClassPath = StringDataType::substringBefore($selector->toString(), '.' . FileSelectorInterface::PHP_FILE_EXTENSION);
                $facadeClassPath = FilePathDataType::normalize(get_class($this));
                return strcasecmp($selectorClassPath, $facadeClassPath) === 0;
            case $selector->isClassname():
                return strcasecmp(trim(get_class($this), "\\"), trim($selector->toString(), "\\")) === 0;
            case $selector->isAlias():
                return strcasecmp($this->getAliasWithNamespace(), $selector->toString()) === 0;
        }
        return false;
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