<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * Event fired when all installers of an app finished backing up.
 * 
 * @event exface.Core.Installer.OnAppBackupEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnAppBackupEvent extends AbstractAppSelectorEvent
{
    private $destinationPath = null;
    
    private $postprocessors = [];
    
    public function __construct(AppSelectorInterface $selector, string $destinationPath)
    {
        parent::__construct($selector);
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
    public function addPostprocessor(iterable $generator) : OnAppBackupEvent
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