<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\AppInstallerInterface;

/**
 * Event fired when an app installer finished backing up.
 * 
 * @event exface.Core.Installer.OnBackupEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnBackupEvent extends AbstractAppInstallerEvent
{
    private $destinationPath = null;
    
    private $postprocessors = [];
    
    public function __construct(AppInstallerInterface $installer, string $destinationPath)
    {
        parent::__construct($installer);
        $this->destinationPath = $destinationPath;
    }
    
    /**
     * 
     * @return string
     */
    public function getDestinationPath() : string
    {
        return $this->destinationPath;
    }
    
    /**
     * 
     * @param iterable $generator
     * @return OnBackupEvent
     */
    public function addPostprocessor(iterable $generator) : OnBackupEvent
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