<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\ServerSoftwareDataType;

/**
 * This installer takes care of file permissions, web.config an other settings required to run on Microsoft IIS.
 * 
 * @author Ralf Mulansky
 *        
 */
class IISServerInstaller extends AbstractAppInstaller
{
    private $webConfigInstaller = null;
    
    private $webConfigVersion = null;
    
    /**
     * 
     * @param SelectorInterface $selectorToInstall
     */
    public function __construct(SelectorInterface $selectorToInstall)
    {
        parent::__construct($selectorToInstall);
        
        // Web.config for IIS servers
        $iisVersion = ServerSoftwareDataType::getServerSoftwareVersion();
        switch (true) {
            case $iisVersion < '10.0':
                $tpl = 'default8.Web.config';
                $this->webConfigVersion = '8.5+';
                break;
            default: 
                $tpl = 'default.Web.config';
                $this->webConfigVersion = '10+';
                break;
        }
        $webconfigInstaller = new FileContentInstaller($this->getSelectorInstalling());
        $webconfigInstaller
        ->setFilePath(Filemanager::pathJoin([$this->getWorkbench()->getInstallationPath(), 'Web.config']))
        ->setFileTemplatePath($tpl)
        ->setMarkerBegin("\n<!-- BEGIN [#marker#] -->")
        ->setMarkerEnd("<!-- END [#marker#] -->");;
        $this->webConfigInstaller = $webconfigInstaller;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $absolute_path) : \Iterator
    {
        yield from $this->webConfigInstaller->backup($absolute_path);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        yield from $this->webConfigInstaller->uninstall();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path): \Iterator
    {
        $indentOuter = $this->getOutputIndentation();
        $indent = $indentOuter . $indentOuter;
        
        yield $indentOuter . "Server configuration for Microsoft IIS " . ServerSoftwareDataType::getServerSoftwareVersion() ?? 'UNKNOWN VERSION' . PHP_EOL;
        
        $this->webConfigInstaller->setOutputIndentation($indent);
        yield $indent . 'Using web.config template for IIS ' . $this->webConfigVersion . PHP_EOL;
        yield from $this->webConfigInstaller->install($source_absolute_path);
        $this->webConfigInstaller->setOutputIndentation($indentOuter);
        
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller::setOutputIndentation()
     */
    public function setOutputIndentation(string $value) : AbstractAppInstaller
    {
        $this->webConfigInstaller->setOutputIndentation($value);
        return parent::setOutputIndentation($value);
    }
}