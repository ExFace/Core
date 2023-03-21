<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\InstallerContainerInterface;
use exface\Core\Factories\PWAFactory;
use exface\Core\DataTypes\StringDataType;

/**
 * Includes PWA models linked to the installed app in its package and re-generates the PWA upon install
 * 
 * @author andrej.kabachnik
 *
 */
class PWAInstaller extends MetaModelAdditionInstaller
{
    /**
     *
     * @param SelectorInterface $selectorToInstall
     * @param InstallerContainerInterface $installerContainer
     */
    public function __construct(SelectorInterface $selectorToInstall, InstallerContainerInterface $installerContainer)
    {
        parent::__construct($selectorToInstall, $installerContainer);
        $modelFolder = 'PWA';
        $this->addModelDataSheet($modelFolder, $this->createModelDataSheet('exface.Core.PWA', 'APP', 'MODIFIED_ON', [
            'REGENERATE_AFTER',
            'GENERATED_ON'
        ]));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path): \Iterator
    {
        $indent = '  ';
        yield from parent::install($source_absolute_path);
        $pwaSheet = $this->createModelDataSheet('exface.Core.PWA', 'APP', 'MODIFIED_ON');
        $pwaSheet->dataRead();
        foreach ($pwaSheet->getRows() as $row) {
            $pwa = PWAFactory::createFromString($this->getWorkbench(), $row['UID']);
            yield 'PWA model generation for "' . $pwa->getName() . '":' . PHP_EOL;
            $generator = $pwa->generateModel();
            foreach ($generator as $msg) {
                // do nothing?
            }
            yield rtrim(StringDataType::indent($generator->getReturn(), $indent)) . PHP_EOL;
        }
    }
}