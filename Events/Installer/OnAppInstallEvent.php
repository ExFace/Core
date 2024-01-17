<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * Event fired when all installers of an app finished installing.
 * 
 * @event exface.Core.Installer.OnAppInstallEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnAppInstallEvent extends AbstractAppSelectorEvent
{
    private $srcPath = null;
    
    private $postprocessors = [];
    
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
    public function addPostprocessor(iterable $generator) : OnAppInstallEvent
    {
        $this->postprocessors[] = $generator;
        return $this;
    }
    
    /**
     *
     * @return iterable[]
     */
    public function getPostprocessors() : array
    {
        return $this->postprocessors;
    }
}