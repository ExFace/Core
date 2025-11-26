<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\ServerSoftwareDataType;

/**
 * Serves as a basis for server installers, by minimizing boilerplate and providing a consistent structure.
 * 
 * @author Georg Bieger
 */
abstract class AbstractServerInstaller extends AbstractAppInstaller
{
    protected InstallerInterface $configInstaller;
    
    /**
     * 
     * @param SelectorInterface $selectorToInstall
     */
    public function __construct(SelectorInterface $selectorToInstall)
    {
        parent::__construct($selectorToInstall);
        
        $configInstaller = (new FileContentInstaller($this->getSelectorInstalling()))
            ->setFilePath(Filemanager::pathJoin([
                $this->getWorkbench()->getInstallationPath(), 
                $this->getConfigFileName()
            ]))
            ->setFileTemplatePath($this->getConfigTemplatePathRelative())
            ->setMarkerBegin("\n{$this->stringToComment('BEGIN [#marker#]')}")
            ->setMarkerEnd($this->stringToComment('END [#marker#]'));
        
        $this->configInstaller = $configInstaller;
    }
    
    /**
     * Returns the filename for the config file created by this installer.
     * For apache this would be `.htaccess` and for IIS it would be `Web.config`.
     * 
     * @return string
     */
    protected abstract function getConfigFileName() : string;

    /**
     * Returns the filepath for the config template relative to the CoreApp folder.
     * This would usually be just the name of the template, e.g. `default.Web.config`.
     * 
     * @return string
     */
    protected abstract function getConfigTemplatePathRelative() : string;

    /**
     * Turns a string into a config comment.
     * 
     * @param string $markerText
     * @return string
     */
    protected abstract function stringToComment(string $markerText) : string;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $absolute_path) : \Iterator
    {
        yield from $this->configInstaller->backup($absolute_path);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        yield from $this->configInstaller->uninstall();
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
        $serverType = ServerSoftwareDataType::getServerSoftwareFamily() ?? 'UNKNOWN SERVER SOFTWARE';
        $serverVersion = ServerSoftwareDataType::getServerSoftwareVersion() ?? 'UNKNOWN VERSION';
        
        yield $indentOuter . "Server configuration for {$serverType} {$serverVersion}:" . PHP_EOL;
        
        $this->configInstaller->setOutputIndentation($indent);
        yield $indent . "Using \"{$this->getConfigTemplatePathRelative()}\" template for {$serverType}." . PHP_EOL;
        yield from $this->configInstaller->install($source_absolute_path);
        $this->configInstaller->setOutputIndentation($indentOuter);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller::setOutputIndentation()
     */
    public function setOutputIndentation(string $value) : AbstractAppInstaller
    {
        $this->configInstaller->setOutputIndentation($value);
        return parent::setOutputIndentation($value);
    }
}