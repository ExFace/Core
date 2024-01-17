<?php
namespace exface\Core\Events\Installer;

/**
 * Event fired right before an app is uninstalled
 * 
 * @event exface.Core.Uninstaller.OnBeforeAppUninstallEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeAppUninstallEvent extends AbstractAppSelectorEvent
{
    private $preprocessors = [];
    
    /**
     * 
     * @param iterable $generator
     * @return OnBeforeBackupEvent
     */
    public function addPreprocessor(iterable $generator) : OnBeforeAppUninstallEvent
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