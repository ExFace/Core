<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\DataTypes\StringDataType;

/**
 * This installer takes care of file permissions an other settings required to run on Microsoft IIS.
 * 
 * @author Ralf Mulansky
 *        
 */
class IISServerInstaller extends AbstractAppInstaller
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $absolute_path) : \Iterator
    {
        return new \EmptyIterator();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        return new \EmptyIterator();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path): \Iterator
    {
        $indent = $this->getOutputIndentation();
        $fm = $this->getWorkbench()->filemanager();
        
        $user = 'IUSR';
        yield $indent . $this->setPermissionsForPath($fm->getPathToDataFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToLogFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToConfigFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToCacheFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToBackupFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToTranslationsFolder(), $user) . PHP_EOL;  
        
        $user = 'IIS_IUSRS';
        yield $indent . $this->setPermissionsForPath($fm->getPathToDataFolder(), $user) . PHP_EOL;
    }
    
    /**
     * 
     * @param string $path
     * @param string $user
     * @return string
     */
    protected function setPermissionsForPath(string $path, string $user): string
    {
        $output = [];
        exec("CACLS {$path} /e /p {$user}:c", $output);
        $shortPath = StringDataType::substringAfter($path, DIRECTORY_SEPARATOR, false, false, true);
        if (empty($output)) {
            return "Permission for the user '{$user}' and folder/file '{$shortPath}' could not be changed!";
        }
        return "Permission for the user '{$user}' and folder/file '{$shortPath}' changed!";
    }
}