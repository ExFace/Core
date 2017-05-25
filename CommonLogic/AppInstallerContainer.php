<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\AppInstallerInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Interfaces\InstallerContainerInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class AppInstallerContainer implements AppInstallerInterface, InstallerContainerInterface
{

    private $app = null;

    private $installers = array();

    public function __construct(AppInterface $app)
    {
        $this->app = $app;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInstallerInterface::getApp()
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getApp()->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public final function install($source_absolute_path)
    {
        $result = '';
        // TODO Dispatch App.Install.Before
        foreach ($this->getInstallers() as $installer) {
            $result .= $installer->install($source_absolute_path);
        }
        // TODO Dispatch App.Install.After
        return $result;
    }

    public final function update($source_absolute_path)
    {
        $result = '';
        // TODO Dispatch App.Install.Before
        foreach ($this->getInstallers() as $installer) {
            $result .= $installer->update($source_absolute_path);
        }
        // TODO Dispatch App.Install.After
        return $result;
    }

    public final function backup($destination_absolute_path)
    {
        $exface = $this->getWorkbench();
        $app = $this->getApp();
        $appAlias = $app->getAlias();
        $appNameResolver = NameResolver::createFromString($appAlias, NameResolver::OBJECT_TYPE_APP, $exface);
        $appPath = $exface->filemanager()->getPathToVendorFolder() . $appNameResolver->getClassDirectory();
        $result = '';
        $app->getWorkbench()->filemanager()->pathConstruct($destination_absolute_path);
        // TODO Dispatch App.Backup.Before
        foreach ($this->getInstallers() as $installer) {
            $result .= $installer->backup($destination_absolute_path);
        }
        $exface->filemanager()->copyDir($appPath, $destination_absolute_path);
        // TODO Dispatch App.Backup.After
        $result .= '';
        return $result;
    }

    public final function uninstall()
    {}

    public function addInstaller(InstallerInterface $installer, $insert_at_beinning = false)
    {
        if ($insert_at_beinning) {
            array_unshift($this->installers, $installer);
        } else {
            $this->installers[] = $installer;
        }
        return $this;
    }

    public function getInstallers()
    {
        return $this->installers;
    }
}