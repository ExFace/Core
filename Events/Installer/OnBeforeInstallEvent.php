<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\AppInstallerInterface;

/**
 * Event fired right before an app installer starts installing.
 * 
 * @event exface.Core.Installer.OnBeforeInstallEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeInstallEvent extends AbstractAppInstallerEvent
{
    private $srcPath = null;
    
    private $preprocessors = [];
    
    public function __construct(AppInstallerInterface $installer, string $srcPath)
    {
        parent::__construct($installer);
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
    public function addPreprocessor(iterable $generator) : OnBeforeInstallEvent
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