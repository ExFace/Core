<?php
namespace exface\Core\Events\Installer;

/**
 * Event fired after all installers of the app finished uninstalling
 * 
 * @event exface.Core.Uninstaller.OnAppUninstallEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnAppUninstallEvent extends AbstractAppSelectorEvent
{
    private $postprocessors = [];
    
    /**
     * 
     * @param iterable $generator
     * @return OnBeforeBackupEvent
     */
    public function addPostprocessor(iterable $generator) : OnAppUninstallEvent
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