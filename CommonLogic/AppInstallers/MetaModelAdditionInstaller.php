<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\InstallerContainerInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

class MetaModelAdditionInstaller extends AbstractAppInstaller
{
    private $modelInstaller = null;
    
    public function __construct(SelectorInterface $selectorToInstall, InstallerContainerInterface $installerContainer)
    {
        parent::__construct($selectorToInstall);
        $this->modelInstaller = $this->findModelInstaller($installerContainer);
        if ($this->modelInstaller === null) {
            throw new RuntimeException('Cannot initialize MetaModelAdditionInstaller: no MetaModelInstaller found!');
        }
    }
    
    public function addModelDataSheet(string $subfolder, DataSheetInterface $sheetToExport, string $lastUpdateAttributeAlias = null) : MetaModelAdditionInstaller
    {
        $this->modelInstaller->addModelDataSheet($subfolder, $sheetToExport, $lastUpdateAttributeAlias);
        return $this;
    }
    
    protected function findModelInstaller(InstallerContainerInterface $container) : ?MetaModelInstaller
    {
        $found = null;
        foreach ($container->getInstallers() as $installer) {
            if ($installer instanceof MetaModelInstaller) {
                $found = $installer;
                break;
            }
            if ($installer instanceof InstallerContainerInterface) {
                if ($found = $this->findModelInstaller($installer)) {
                    break;
                }
            }
        }
        return $found;
    }
    
    public function backup(string $absolute_path): \Iterator
    {
        yield from [];
    }

    public function uninstall(): \Iterator
    {
        yield from [];
    }

    public function install(string $source_absolute_path): \Iterator
    {
        yield from [];
    }
}