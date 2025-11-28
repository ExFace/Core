<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\ServerSoftwareDataType;

/**
 * This installer takes care of file permissions, web.config and other settings required to run on Microsoft IIS.
 * 
 * @author Ralf Mulansky
 *        
 */
class IISServerInstaller extends AbstractServerInstaller
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path): \Iterator
    {
        yield from parent::install($source_absolute_path);

        $fm = $this->getWorkbench()->filemanager();
        $user = 'IUSR';
        
        $indentOuter = $this->getOutputIndentation();
        $indent = $indentOuter . $indentOuter;

        yield $indent . $this->setPermissionsForPath($fm->getPathToDataFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToLogFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToConfigFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToCacheFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToBackupFolder(), $user) . PHP_EOL;
        yield $indent . $this->setPermissionsForPath($fm->getPathToTranslationsFolder(), $user) . PHP_EOL;  
        
        $user = 'IIS_IUSRS';
        yield $indent . $this->setPermissionsForPath($fm->getPathToDataFolder(), $user) . PHP_EOL;
    }

    protected function getServerFamily() : string
    {
        return 'Microsoft IIS';
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

    protected function getConfigFileName(): string
    {
        return 'Web.config';
    }

    /**
     * @inheritDoc
     */
    protected function getConfigTemplatePathRelative(): string
    {
        // Web.config for IIS servers
        $iisVersion = ServerSoftwareDataType::getServerSoftwareVersion();
        return match (true) {
            $iisVersion < '10.0' => 
                'default8.Web.config',
            default => 
                'default.Web.config',
        };
    }

    /**
     * @inheritDoc
     */
    protected function stringToComment(string $comment): string
    {
        return "<!-- {$comment} -->";
    }
}