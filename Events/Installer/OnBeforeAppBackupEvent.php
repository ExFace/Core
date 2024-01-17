<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * Event fired when an app is about to be backed up.
 * 
 * @event exface.Core.Installer.OnBeforeAppBackupEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeAppBackupEvent extends AbstractAppSelectorEvent
{
    private $destinationPath = null;
    
    private $preprocessors = [];
    
    public function __construct(AppSelectorInterface $selector, string $destinationPath)
    {
        parent::__construct($selector);
        $this->destinationPath = $destinationPath;
    }
    
    public function getDestinationPath() : string
    {
        return $this->destinationPath;
    }
    
    public function addPreprocessor(iterable $generator) : OnBeforeAppBackupEvent
    {
        $this->preprocessors[] = $generator;
        return $this;
    }
    
    public function getPreprocessors() : array
    {
        return $this->preprocessors;
    }
}