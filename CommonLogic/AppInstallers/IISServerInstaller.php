<?php
namespace exface\Core\CommonLogic\AppInstallers;


use exface\Core\DataTypes\StringDataType;

/**
 * This installer gives the user `IIS_USERNAME`, default its 'IUSR', `the change` permissions for the folders `data`, `log`, `config`, `cache`, `backup`, `translations` and all their subfolders.
 * The permissions are set via the cmd command `CALCS`.
 * 
 * @author Ralf Mulansky
 *        
 */
class IISServerInstaller extends AbstractAppInstaller
{
    const IIS_USERNAME = IUSR;
    
    public function backup(string $absolute_path) : \Iterator
    {
        return new \EmptyIterator();
    }
    
    public function uninstall() : \Iterator
    {
        return new \EmptyIterator();
    }

    public function install(string $source_absolute_path): \Iterator
    {
        $indent = $this->getOutputIndentation();
        yield $indent . $this->setPermissionsForDataFolder() . PHP_EOL;
        yield $indent . $this->setPermissionsForLogsFolder() . PHP_EOL;
        yield $indent . $this->setPermissionsForConfigFolder() . PHP_EOL;
        yield $indent . $this->setPermissionsForCacheFolder() . PHP_EOL;
        yield $indent . $this->setPermissionsForBackupFolder() . PHP_EOL;
        yield $indent . $this->setPermissionsForTranslationsFolder() . PHP_EOL;                
    }
    
    protected function setPermissionForPath(string $path): string
    {
        $output = [];
        $user = self::IIS_USERNAME;
        exec("CACLS {$path} /e /t /p {$user}:c", $output);
        $shortPath = StringDataType::substringAfter($path, DIRECTORY_SEPARATOR, false, false, true);
        if (empty($output)) {
            return "Permission for the user '{$user}' and folder/file '{$shortPath}' could not be changed!";
        }
        return "Permission for the user '{$user}' and folder/file '{$shortPath}' changed!";
    }
    
    protected function setPermissionsForDataFolder() : string
    {
        $path = $this->getWorkbench()->filemanager()->getPathToDataFolder();
        return $this->setPermissionsForPath($path);        
    }
    
    protected function setPermissionsForLogsFolder() : string
    {
        $path = $this->getWorkbench()->filemanager()->getPathToLogFolder();
        return $this->setPermissionsForPath($path); 
    }
    
    protected function setPermissionsForConfigFolder() : string
    {
        $path = $this->getWorkbench()->filemanager()->getPathToConfigFolder();
        return $this->setPermissionsForPath($path);
    }
    
    protected function setPermissionsForCacheFolder() : string
    {
        $path = $this->getWorkbench()->filemanager()->getPathToCacheFolder();
        return $this->setPermissionsForPath($path);
    }

    protected function setPermissionsForBackupFolder() : string
    {
        $path = $this->getWorkbench()->filemanager()->getPathToBackupFolder();
        return $this->setPermissionsForPath($path);
    }
    
    protected function setPermissionsForTranslationsFolder() : string
    {
        $path = $this->getWorkbench()->filemanager()->getPathToTranslationsFolder();
        return $this->setPermissionsForPath($path);
    }
    
}