<?php
namespace exface\Core\Facades\AbstractPWAFacade;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\UrlDataType;

/**
 * This installer can be used to add code for a specific facade to the central PWA ServiceWorker.
 * 
 * ## How it works
 * 
 * 1) The facade app instantiates this installer and provides a `ServiceWorkerBuilder`, that produces
 * the the JS code for routes and imports, that need to be added to the ServiceWorker for the facade.
 * 2) The installer maintains a `ServiceWorker.config.json` where it keeps track of ServiceWorker
 * code from each app separately - to avoid conflicts. Every install/uninstall operation updates the
 * respecitve app's section in this config file, but does not affect the other apps in it.
 * 3) After each install/uninstall the installer will generate a `ServiceWorker.js` from all the code
 * in the config file. Thus, every time an app is installed or removed, it's part in the `ServiceWorker.js`
 * is updated.
 * 
 * Using a `ServiceWorkerBuilder` allows to separate the responsibility for generating the JS code from
 * the installer's logic concerned with the location of the ServiceWorker file, common routes, etc.
 *        
 * @author Andrej Kabachnik
 *        
 */
class ServiceWorkerInstaller extends AbstractAppInstaller
{
    private $serviceWorkerBuilder = null;
    
    private $disabled = false;
    
    /**
     * 
     * @param SelectorInterface $selectorToInstall
     * @param ServiceWorkerBuilder $builder
     */
    public function __construct(SelectorInterface $selectorToInstall, ServiceWorkerBuilder $builder)
    {
        parent::__construct($selectorToInstall);
        $this->serviceWorkerBuilder = $builder;
    }
    
    /**
     * 
     * @param SelectorInterface $selectorToInstall
     * @param ConfigurationInterface $config
     * 
     * @return ServiceWorkerInstaller
     */
    public static function fromConfig(SelectorInterface $selectorToInstall, ConfigurationInterface $config) : ServiceWorkerInstaller
    {
        $builder = new ServiceWorkerBuilder('vendor');
        
        foreach ($config->getOption('INSTALLER.SERVICEWORKER.ROUTES') as $id => $uxon) {
            $builder->addRouteFromUxon($id, $uxon);
        }
        
        if ($config->hasOption('INSTALLER.SERVICEWORKER.IMPORTS')) {
            foreach ($config->getOption('INSTALLER.SERVICEWORKER.IMPORTS') as $path) {
                $builder->addImport($path);
            }
        }
        
        $installer = new self($selectorToInstall, $builder);
        
        if ($config->hasOption('INSTALLER.SERVICEWORKER.DISABLED')) {
            $installer->setDisabled($config->getOption('INSTALLER.SERVICEWORKER.DISABLED'));
        }
        
        return $installer;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path) : \Iterator
    {
        $indent = $this->getOutputIndentation();
        if ($this->isDisabled()) {
            $config = $this->uninstallFromConfig($this->getApp(), $this->getConfig());
        } else {
            $config = $this->installToConfig($this->getApp(), $this->getServiceWorkerBuilder(), $this->getConfig());
        }
        yield $indent . $this->buildServiceWorker($config) . PHP_EOL;
    }
    
    protected function buildUrlToWorkbox() : string
    {
        return $this->getWorkbench()->getConfig()->getOption('FACADES.ABSTRACTPWAFACADE.WORKBOX_VENDOR_PATH');
    }
    
    protected function buildServiceWorker(ConfigurationInterface $config) : string
    {
        $workboxUrl = $this->buildUrlToWorkbox();;
        $builder = new ServiceWorkerBuilder('vendor', $workboxUrl);
        
        // Add imports
        if ($config->hasOption('_IMPORTS')) {
            foreach ($config->getOption('_IMPORTS')->getPropertiesAll() as $path) {
                $builder->addImport($path);
            }
        }
        // Add core code first
        if ($config->hasOption('EXFACE.CORE')) {
            $builder->addCustomCode($config->getOption('EXFACE.CORE'), 'EXFACE.CORE');
        }
        // Now add all the other apps
        foreach ($config->exportUxonObject() as $appAlias => $code) {
            if ($appAlias !== '_IMPORTS' && $appAlias !== 'EXFACE.CORE') {
                $builder->addCustomCode($code, $appAlias);
            }
        }
        
        try {
            $path = $this->saveServiceWorker($builder->buildJsLogic(), $builder->buildJsImports());
            $result = 'ServiceWorker "' . $path . '" generated.';
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $result = 'ERROR: Failed to generate ServiceWorker "' . $path . '": ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 
     * @param string $jsCode
     * @param string $imports
     * @return string
     */
    protected function saveServiceWorker(string $jsCode, string $imports = '') : string
    {
        $code = <<<JS
/**
 * This file is generated by the ServiceWorkerInstaller! Changes will be overwritten automatically!
 *
 * To change caching options refer to the configuration of the respecitve app!
 *
 */
{$imports}

{$jsCode}

JS;

$filename = $this->buildUrlToServiceWorker();
$path = $this->getWorkbench()->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR . FilePathDataType::normalize($filename, DIRECTORY_SEPARATOR);
file_put_contents($path, $code);
return $filename;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        $this->uninstallFromConfig($this->getApp(), $this->getConfig());        
        return 'ServiceWorker configuration removed';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $destination_absolute_path) : \Iterator
    {
        return '';
    }
    
    /**
     * 
     * @return ServiceWorkerBuilder|NULL
     */
    protected function getServiceWorkerBuilder() : ?ServiceWorkerBuilder
    {
        return $this->serviceWorkerBuilder;
    }
    
    protected function getConfig() : ConfigurationInterface
    {
        $wb = $this->getWorkbench();
        $config = ConfigurationFactory::create($wb);
        $config->loadConfigFile($wb->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR . 'ServiceWorker.config.json', $this->getConfigScope());
        return $config;
    }
    
    /**
     * 
     * @param AppInterface $app
     * @param ServiceWorkerBuilder $swBuilder
     * @param ConfigurationInterface $config
     * @return ConfigurationInterface
     */
    protected function installToConfig(AppInterface $app, ServiceWorkerBuilder $swBuilder, ConfigurationInterface $config) : ConfigurationInterface
    {
        $config->setOption($app->getAliasWithNamespace(), $swBuilder->buildJsLogic(), $this->getConfigScope());
        
        try {
            $currentImports = $config->getOption('_IMPORTS')->toArray();
        } catch (ConfigOptionNotFoundError $e) {
            $currentImports = [];
        }
        
        $imports = $swBuilder->getImports();
        
        // Look for duplicates among the imports. If an import path matches another one partially
        // (e.g. same library taken from different locations), keep only one of them - the one
        // added last.
        foreach (array_reverse($currentImports) as $currentImport) {
            $matchFound = false;
            foreach ($imports as $import) {
                switch (true) {
                    case strcasecmp($import, $currentImport) === 0:
                    case StringDataType::endsWith($currentImport, $import, false):
                        $matchFound = true;
                        break 2;
                }
            }
            if ($matchFound === false) {
                array_unshift($imports, $currentImport);
            }
        }
        
        $config->setOption('_IMPORTS', $imports, $this->getConfigScope());
        
        return $this->addCommonConfig($config);
    }
    
    protected function uninstallFromConfig(AppInterface $app, ConfigurationInterface $config) : ConfigurationInterface
    {
        $config->unsetOption($app->getAliasWithNamespace(), $this->getConfigScope());
        return $this->addCommonConfig($config);
    }
    
    protected function addCommonConfig(ConfigurationInterface $config) : ConfigurationInterface
    {
        $builder = new ServiceWorkerBuilder('vendor', $this->buildUrlToWorkbox());
        $workbenchConfig = $this->getWorkbench()->getConfig();
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $msgSyncedJs = json_encode($translator->translate('OFFLINE.ACTIONS.SYNC_COMPLETE'));
        $msgSyncFailedJs = json_encode($translator->translate('OFFLINE.ACTIONS.SYNC_FAILED'));
        
        foreach ($workbenchConfig->getOption('FACADES.ABSTRACTPWAFACADE.SERVICEWORKER_COMMON_ROUTES') as $id => $uxon) {
            $builder->addRouteFromUxon($id, $uxon);
        }
        $js = <<<JS
self.addEventListener('sync', function(event) {
    if (event.tag === 'OfflineActionSync') {
		event.waitUntil(
			exfPreloader.getActionQueueIds('offline')
			.then(function(ids){
				return exfPreloader.syncActionAll(ids)
			})
			.then(function(){
				self.clients.matchAll()
				.then(function(all) {
					all.forEach(function(client) {
						client.postMessage({$msgSyncedJs});
					});
				});
				return;
			})
			.catch(function(error){
				console.error('Could not sync offline actions completely - scheduled for the next time.', error);
				self.clients.matchAll()
				.then(function(all) {
					all.forEach(function(client) {
						client.postMessage({$msgSyncFailedJs});
					});
				});
				return Promise.reject(error);
			})
		)
    }
});
JS;
        $builder->addCustomCode($js, 'Handle OfflineActionSync Event');
        $config->setOption($this->getWorkbench()->getCoreApp()->getAliasWithNamespace(), $builder->buildJsLogic(), $this->getConfigScope());
        
        try {
            $currentImports = $config->getOption('_IMPORTS')->toArray();
        } catch (ConfigOptionNotFoundError $e) {
            $currentImports = [];
        }
        $imports = array_merge($currentImports, $workbenchConfig->getOption('FACADES.ABSTRACTPWAFACADE.SERVICEWORKER_COMMON_IMPORTS')->toArray());
        $config->setOption('_IMPORTS', array_unique($imports), $this->getConfigScope());
        
        return $config;
    }
    
    protected function getConfigScope() : string
    {
        return AppInterface::CONFIG_SCOPE_SYSTEM;   
    }
    
    protected function isDisabled() : bool
    {
        return $this->disabled;
    }
    
    public function setDisabled(bool $trueOrFalse) : ServiceWorkerInstaller
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function buildUrlToServiceWorker() : string
    {
        return $this->getWorkbench()->getConfig()->getOption("FACADES.ABSTRACTPWAFACADE.SERVICEWORKER_FILENAME");
    }
}