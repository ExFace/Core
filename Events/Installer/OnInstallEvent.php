<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\AppInstallerInterface;

/**
 * Event fired when an app installer finished installing.
 * 
 * @event exface.Core.Installer.OnInstallEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnInstallEvent extends AbstractAppInstallerEvent
{
    private $srcPath = null;
    
    private $postprocessors = [];
    
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
    public function addPostprocessor(iterable $generator) : OnInstallEvent
    {
        $this->postprocessors[] = $generator;
        return $this;
    }
    
    /**
     *
     * @return array
     */
    public function getPostprocessors() : array
    {
        return $this->postprocessors;
    }
}