<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * Event fired right before an app is installed
 * 
 * @event exface.Core.Installer.OnBeforeAppInstallEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeAppInstallEvent extends AbstractAppSelectorEvent
{
    private $srcPath = null;
    
    private $preprocessors = [];
    
    public function __construct(AppSelectorInterface $selector, string $srcPath)
    {
        parent::__construct($selector);
        $this->srcPath = $srcPath;
    }
    
    /**
     * 
     * @return string
     */
    public function getSourcePath() : string
    {
        return $this->srcPath;
    }
    
    /**
     * 
     * @param iterable $generator
     * @return OnBeforeBackupEvent
     */
    public function addPreprocessor(iterable $generator) : OnBeforeAppInstallEvent
    {
        $this->preprocessors[] = $generator;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    public function getPreprocessors() : array
    {
        return $this->preprocessors;
    }
}