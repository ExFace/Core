<?php
namespace exface\Core\CommonLogic\AppInstallers;


use exface\Core\DataTypes\JsonDataType;
use exface\Core\Interfaces\Selectors\SelectorInterface;

/**
 * Registeres a CLI facade command as scheduled task in the current operating system.
 * 
 * @author Andrej Kabachnik
 *        
 */
class StaticEventListenerInstaller extends AbstractAppInstaller
{
    private array $listeners;
    private $filepath;
    
    public function __construct(SelectorInterface $selectorToInstall, string $pathRelativeToInstallationFolder)
    {
        parent::__construct($selectorToInstall);
        $this->filepath = $selectorToInstall->getWorkbench()->getInstallationPath() . DIRECTORY_SEPARATOR . $pathRelativeToInstallationFolder;
    }

    public function backup(string $absolute_path) : \Iterator
    {
        return new \EmptyIterator();
    }
    
    public function uninstall() : \Iterator
    {
        // TODO remove event listeners because otherwise they will be called when their app is not installed anymore
        return new \EmptyIterator();
    }

    public function install(string $source_absolute_path): \Iterator
    {
        $targetFile = $this->getTargetPath();
        if (! file_exists($targetFile)) {
            $configArray = $this->getTemplate();
        } else {
            $configArray = JsonDataType::decodeJson(file_get_contents($targetFile));
        }
        // TODO add stuff from $this->getListeners() and save file
        // IDEA also register listeners in $workbench->eventManger() to make them active right away???
    }
    
    protected function getListeners() : array
    {
        return $this->listeners;
    }
    
    protected function getTargetPath() : string
    {
        return $this->filepath;
    }
    
    protected function getTemplate() : array
    {
        $tplFile = $this->getWorkbench()->getCoreApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Evetns.config.php';
        return JsonDataType::decodeJson($tplFile);
    }
}