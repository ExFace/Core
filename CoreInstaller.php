<?php
namespace exface\Core;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\DateTimeDataType;

/**
 *
 * @method CoreApp getApp()
 *        
 * @author Andrej Kabachnik
 *        
 */
class CoreInstaller extends AbstractAppInstaller
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path) : \Iterator
    {
        $indent = $this->getOutputIndentation();
        // Install model DB
        $modelLoader = $this->getWorkbench()->model()->getModelLoader();
        $modelLoaderInstaller = $modelLoader->getInstaller();
        yield from $modelLoaderInstaller->install($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $modelLoaderInstaller->getSelectorInstalling()->getFolderRelativePath());
        $this->getWorkbench()->model()->clearCache();
        // Clear all model caches to force the workbench to load everything again. This is important because
        // Objects may have been loaded previously (e.g. the exface.Core.APP object is required to instatiate
        // the installers.
        $modelLoader->clearCache();        
        
        // Add required files to root folder
        yield $indent . $this->createApiPhp($source_absolute_path);
        yield $indent . $this->removeLegacyFiles($source_absolute_path);
        
        $needRestart = $this->getWorkbench()->isInstalled() === false;
        
        $this->getWorkbench()->getContext()->getScopeInstallation()->setVariable('last_metamodel_install', DateTimeDataType::now());
        
        if ($needRestart) {
            $this->getWorkbench()->stop();
            $this->getWorkbench()->start();
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        return 'Uninstall not implemented for installer "' . $this->getSelectorInstalling()->getAliasWithNamespace() . '"!';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $destination_absolute_path) : \Iterator
    {
        return 'Backup not implemented for' . $this->getSelectorInstalling()->getAliasWithNamespace() . '!';
    }
    
    protected function createApiPhp(string $source_absolute_path) : string
    {
        $result = '';
        // Copy default .htaccess to the root of the installation
        $file = Filemanager::pathJoin([$this->getWorkbench()->getInstallationPath(), 'api.php']);
        if (! file_exists($file)) {
            $content = <<<PHP
<?php 
error_reporting(E_ALL & ~E_NOTICE);
require_once('vendor/exface/Core/index.php');
?>
PHP;
            try {
                file_put_contents($file, $content);
                $result .= "Generated default api.php file in plattform root." . PHP_EOL;
            } catch (\Exception $e) {
                $result .= "Failed to copy default api.php file: " . $e->getMessage() . ' in ' . $e->getFile() . ' at ' . $e->getLine() . '.' . PHP_EOL;
            }
        }
        
        return $result;
    }
    
    protected function removeLegacyFiles(string $source_absolute_path) : string
    {
        $files = [
            'exface.php'
        ];
        foreach ($files as $file) {
            $filepath = Filemanager::pathJoin([$this->getWorkbench()->getInstallationPath(), $file]);
            if (file_exists($filepath)) {
                try {
                    unlink($filepath);
                } catch (\Throwable $e) {
                    return 'Could not remove legacy file "' . $filepath . '" - please delete the file manually!' . PHP_EOL;
                }
            }
        }
        return '';
    }
}
?>