<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\AppInstallerInterface;

/**
 * Event fired when an app installer is about to start backing up.
 * 
 * @event exface.Core.Installer.OnBeforeBackupEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeBackupEvent extends AbstractAppInstallerEvent
{
    private $destinationPath = null;
    
    private $preprocessors = [];
    
    public function __construct(AppInstallerInterface $installer, string $destinationPath)
    {
        parent::__construct($installer);
        $this->destinationPath = $destinationPath;
    }
    
    public function getDestinationPath() : string
    {
        return $this->destinationPath;
    }
    
    public function addPreprocessor(iterable $generator) : OnBeforeBackupEvent
    {
        $this->preprocessors[] = $generator;
        return $this;
    }
    
    public function getPreprocessors() : array
    {
        return $this->preprocessors;
    }
}