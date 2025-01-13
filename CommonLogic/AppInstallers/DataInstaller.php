<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Behaviors\ValidatingBehavior;
use exface\Core\DataTypes\TextDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Behaviors\TimeStampingBehavior;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\QueryBuilder\RowDataArrayFilter;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Behaviors\ModelValidatingBehavior;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\EncryptedDataType;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Exceptions\EncryptionError;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Events\Installer\OnAppBackupEvent;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\DateDataType;

/**
 * Saves all data of selected objects, that is related to the an app, in a subfolder of the app.
 * 
 * ## Configuration
 * 
 * By default, this installer offers the following configuration options to control
 * it's behavior on a specific installation. These options can be added to the config
 * of the app being installed.
 * 
 * - `INSTALLER.DATAINSTALLER.DISABLED` - set to TRUE to disable this installer
 * completely (e.g. if you wish to manage the database manually).
 * 
 * For any installer extending from the DataInstaller you have to change the `DATAINSTALLER`
 * part in the configuration option with the classe name of the extending installer.
 * 
 * ### Include some master data in an app package
 * 
 * This will place a JSON file for every added object in the folder `Data` inside the app.
 * 
 * Since `addDataToMerge()` is used, the installer will not delete any data - just add
 * or update it. To replace the entire data completely use `addDataToReplace()` as as
 * shown in the next example  
 * 
 * ```
 *  $dataInstaller = new DataInstaller($this->getSelector(), 'Data');
 *  $dataInstaller->addDataToMerge('axenox.ETL.webservice_type', 'CREATED_ON', 'app');
 *  $installer->addInstaller($dataInstaller);
 *      
 * ```
 * 
 * ### Include a more complex model
 * 
 * This will produce a more complex folder structure - in this case, a subfolder for every
 * flow with JSON files for the flow and its steps in the folder `Data/Flows`.
 * 
 * ```
 *  $dataInstaller = new DataInstaller($this->getSelector(), 'Data');
 *  $dataInstaller->addDataToReplace('axenox.ETL.flow', 'CREATED_ON', 'app', [], 'Flows/[#alias#]/01_flow.json');
 *  $dataInstaller->addDataToReplace('axenox.ETL.step', 'CREATED_ON', 'flow__app', [], 'Flows/[#flow__alias#]/02_steps.json');
 *  $installer->addInstaller($dataInstaller);
 * ```
 * 
 * ## Export format
 * 
 * Every data sheet is exported as one or more prettyprinted JSON files. This depends on whether 
 * there are placeholders in the path. These placeholders will split the JSON into multiple files.
 * 
 * In contrast to the regular UXON-export of a data sheet where the value of each column is
 * stored as a string, the model sheet columns containing UXON have prettyprinted JSON values.
 * This makes it easier to identify changes in larger UXON objects like default editors, aciton
 * configurations, etc. Same goes for multiline text: it is transformed into JSON arrays in order
 * to keep the lines visible in file diffs.
 * 
 * When installing, the files are processed in alphabetical order - more precisely in the order
 * of the numerc filename prefixes. For each entity type a data sheet is instantiated and 
 * `DataSheetInterface::dataReplaceByFilters()` is preformed filtered by the app - this makes
 * sure all possibly existing entities bound to this app are completely replaced by the contents
 * of the data sheet.
 * 
 * ### Folder structure
 * 
 * As shown above, the installer can be easily customized to save data in different file and folder
 * structures. Each meta object is always exported and imported as a single DataSheet. However, you
 * can split that sheet and save its contents in multiple files - thus, greatly simplifying version
 * control. It is a good idea, to split data files in a way, that only closely related data is
 * placed in the same file.
 * 
 * You can even change the folder sturcture without breaking backwards compatibility! 
 * 
 * ### Encryption
 * 
 * Every content of a attribute with `EncryptedDataType` as data type will be exported as an encrypted string.
 * The used encryption salt will either be build from the app uid or you can provide a custom salt.
 * The custom salt has to be placed in the `Encryption.config.json` file in the `config` folder with the app alias (with namespace) as key.
 * The salt has to be 32 characters long. When importing the metamodell on a different PowerUi installation you will also need that config
 * file with that key you used for encryption.
 * 
 * You can use the followign website to create a salt: `http://www.unit-conversion.info/texttools/random-string-generator/`
 * 
 * CAREFUL: If you lose the used custom salt for encryption during the export you will not be able to restore the encrypted
 * data and the affected data will be lost.
 * 
 * ## Behaviors
 * 
 * NOTE: The `TimeStampingBehavior` of the model objects is disabled before install, so the
 * create/update stamps of the exported model are saved correctly.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataInstaller extends AbstractAppInstaller
{
    const ENCRYPTION_CONFIG_FILE = 'Encryption.config.json';
    
    const CONFIG_OPTION_DISABLED = 'DISABLED';
    
    const CONFIG_OPTION_PREFIX = 'INSTALLER';

    const SPLIT_TEXT_COMMENT = '// Multiline text delimited by ';
    
    private $path = 'Data';
    
    private $dataSheets = [];
    
    private $salt = null;
    
    private $dataDefs = [];
    
    private $filenameIndexStart = 0;
    
    private $className = null;
    
    /**
     * 
     * @param SelectorInterface $selectorToInstall
     * @param string $folderPath
     */
    public function __construct(SelectorInterface $selectorToInstall, string $folderPath = 'Data')
    {
        parent::__construct($selectorToInstall);
        $this->setDataFolderPath($folderPath);
    }
    
    /**
     *
     * @param string $selector
     * @return bool
     */
    protected function isInstallableObject(string $selector) : bool
    {
        return array_key_exists($selector, $this->dataDefs);
    }

    /**
     *
     * @param string $source_absolute_path
     * @return string
     */
    public function install(string $source_absolute_path) : \Iterator 
    {       
        $indent = $this->getOutputIndentation();
        yield $indent . $this->getName() . ":" . PHP_EOL;
        if ($this->isDisabled() === true) {
            yield $indent . $indent . $this->getClassName() . ' disabled' . PHP_EOL;
            return;
        }
        
        $srcPath = $this->getDataFolderPathAbsolute($source_absolute_path);
        
        if (is_dir($srcPath)) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            yield from $this->installModel($srcPath, $transaction, $indent);
            $transaction->commit();
        } else {
            yield $indent . "No {$this->getName()} files to install" . PHP_EOL;
        }
    }
    
    /**
     * 
     * @param string $srcPath
     * @param DataTransactionInterface $transaction
     * @param string $indent
     * @throws InstallerRuntimeError
     * @return \Generator
     */
    protected function installModel(string $srcPath, DataTransactionInterface $transaction, string $indent) : \Generator
    {
        $modelChanged = false;
        foreach ($this->readModelSheetsFromFolders($srcPath) as $data_sheet) {
            try {
                
                // Remove columns, that are not attributes. This is important to be able to import changes on the meta model itself.
                // The trouble is, that after new properties of objects or attributes are added, the export will already contain them
                // as columns, which would lead to an error because the model entities for these columns are not there yet.
                foreach ($data_sheet->getColumns() as $column) {
                    switch (true) {
                        // Ignore non-attributes
                        case ! $column->isAttribute():
                        // Ignore attributes, that do not exist (anymore)
                        case ! $column->getMetaObject()->hasAttribute($column->getAttributeAlias()):
                        // Ignore columns with related data - see. `exportModelFile()` for explanation.
                        case $column->getAttribute()->isRelated():
                            $data_sheet->getColumns()->remove($column);
                            break;
                    }
                }
                
                $this->disableBehaviors($data_sheet);
                
                // There were cases, when the attribute, that is being filtered over was new, so the filters
                // did not work (because the attribute was not there). The solution is to run an update
                // with create fallback in this case. This will cause filter problems, but will not delete
                // obsolete instances. This is not critical, as the probability of this case is extremely
                // low in any case and the next update will turn everything back to normal.
                if (! $this->checkFiltersMatchModel($data_sheet->getFilters())) {
                    $data_sheet->getFilters()->removeAll();
                    $counter = $data_sheet->dataUpdate(true, $transaction);
                } else {
                    $deleteLocalRowsThatAreNotInTheSheet = ! $data_sheet->getFilters()->isEmpty();
                    $counter = $data_sheet->dataReplaceByFilters($transaction, $deleteLocalRowsThatAreNotInTheSheet);
                }
                
                if ($counter > 0) {
                    $modelChanged = true;
                    yield $indent . $indent . $data_sheet->getMetaObject()->getName() . " - " . $counter . PHP_EOL;
                }
            } catch (\Throwable $e) {
                throw new InstallerRuntimeError($this, 'Failed to install ' . $data_sheet->getMetaObject()->getAlias() . '-sheet: ' . $e->getMessage(), null, $e);
            }
        }
        
        if ($modelChanged === false) {
            yield $indent.$indent."No changes found" . PHP_EOL;
        }
        
        return $modelChanged;
    }
    
    protected function disableBehaviors(DataSheetInterface $data_sheet) : DataInstaller
    {
        $obj = $data_sheet->getMetaObject();
        
        // Disable timestamping behavior because it will prevent multiple installations of the same
        // model since the first install will set the update timestamp to something later than the
        // timestamp saved in the model files
        foreach ($obj->getBehaviors()->getByPrototypeClass(TimeStampingBehavior::class) as $behavior) {
            $behavior->disable();
            // Make sure to explicitly disable fixed values on update-columns
            if ($behavior->hasUpdatedOnAttribute()) {
                if ($col = $data_sheet->getColumns()->getByAttribute($behavior->getUpdatedOnAttribute())) {
                    $col->setIgnoreFixedValues(true);
                }
            }
            if ($behavior->hasUpdatedByAttribute()) {
                if ($col = $data_sheet->getColumns()->getByAttribute($behavior->getUpdatedByAttribute())) {
                    $col->setIgnoreFixedValues(true);
                }
            }
        }
        
        // Prevent duplicates behavior
        /*foreach ($obj->getBehaviors()->getByPrototypeClass(PreventDuplicatesBehavior::class) as $behavior) {
            $behavior->disable();
        }*/

        // ValidatingBehavior - if older model do not pass validation rules, they still need to be installd
        // to be fixed!!!
        foreach ($obj->getBehaviors()->getByPrototypeClass(ValidatingBehavior::class) as $behavior) {
            $behavior->disable();
        }
        
        // Disable model validation because it would instantiate all objects when the object sheet is being saved,
        // which will attempt to load an inconsistent model (e.g. because the attributes were not yet updated
        // at this point.
        foreach ($obj->getBehaviors()->getByPrototypeClass(ModelValidatingBehavior::class) as $behavior) {
            $behavior->disable();
        }
        return $this;
    }

    /**
     *
     * @param string $destinationAbsolutePath
     * @return string
     */
    public function backup(string $destinationAbsolutePath) : \Iterator
    {
        $idt = $this->getOutputIndentation();
        $app = $this->getApp();
        $dir = $this->getDataFolderPathAbsolute($destinationAbsolutePath);
        
        // Remove any old files AFTER the data sheets were read successfully
        // in order to keep old data on errors.
        $dirOld = $this->moveFolderToTemp($dir);
        
        
        // Make sure, the destination folder is there and empty (to remove
        // files, that are not neccessary anymore)
        $app->getWorkbench()->filemanager()->pathConstruct($dir);
        
        // Save each data sheet as a file and additionally compute the modification date of the last modified model instance and
        // the MD5-hash of the entire model definition (concatennated contents of all files). This data will be stored in the composer.json
        // and used in the installation process of the package
        foreach ($this->getModelSheets() as $nr => $ds) {
            $files = $this->exportModelFile($dir, $ds, $dirOld);
            /* TODO use the output of the exporter somehow - e.g.:
            foreach ($files as $file) {
                yield $idt . $idt . $file;
            }*/
        }
        
        yield $idt . 'Created ' . $this->getName() . ' backup for "' . $app->getAliasWithNamespace() . '".' . PHP_EOL;
    }
    
    /**
     * 
     * @param OnAppBackupEvent $event
     */
    public function handleBackupFinished(OnAppBackupEvent $event)
    {
        if ($event->getAppSelector() !== $this->getSelectorInstalling()) {
            return;
        }
        $dataFolderPath = $this->getDataFolderPathAbsolute($event->getDestinationPath());
        $event->addPostprocessor($this->getTempFolderCleaner($dataFolderPath));
        return;
    }
    
    /**
     * 
     * @param string $destinationPathAbsolute
     * @return string|NULL
     */
    protected function moveFolderToTemp(string $destinationPathAbsolute) : ?string
    {
        $tmpDir = $this->getTempFolderPath($destinationPathAbsolute);
        if ($tmpDir !== null) {
            rename($destinationPathAbsolute, $tmpDir);
            // Remove temp folder with possibly remaining old files after the app is installed
            // It is important to do this after all installers were run as there may be subfolders
            // inside this installers folder, that are handled by other installers - e.g. the
            // UiPageInstaller run after the MetaModelInstaller or the old MetaModelAdditionInstaller.
            $this->getWorkbench()->eventManager()->addListener(OnAppBackupEvent::getEventName(), [$this, 'handleBackupFinished']);
        }
        return $tmpDir;
    }
    
    /**
     * 
     * @param string $destinationPathAbsolute
     * @return string|NULL
     */
    protected function getTempFolderPath(string $destinationPathAbsolute) : ?string
    {
        $tmpFolder = $destinationPathAbsolute . '.tmp';
        return is_dir($destinationPathAbsolute) || is_dir($tmpFolder) ? $tmpFolder : null;
    }
    
    /**
     * 
     * @param string $dataFolderPath
     * @return \Generator
     */
    protected function getTempFolderCleaner(string $dataFolderPath) : \Generator
    {
        $tmpPath = $this->getTempFolderPath($dataFolderPath);
        if ($tmpPath !== null && is_dir($tmpPath)) {
            Filemanager::deleteDir($tmpPath);
            yield $this->getOutputIndentation() . 'Cleaned up temporary folders';
        }
        return;
    }
    
    /**
     * 
     * @param int $firstIdx
     * @return DataInstaller
     */
    public function setFilenameIndexStart(int $firstIdx) : DataInstaller
    {
        $this->filenameIndexStart = $firstIdx;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getName() : string
    {
        $path = $this->getDataFolderPathRelative();
        $folder = StringDataType::substringAfter($path, DIRECTORY_SEPARATOR, $path, false, true);
        if (mb_strtoupper($folder) === $folder) {
            return $folder;
        }
        return ucfirst(str_replace('_', ' ', StringDataType::convertCaseCamelToUnderscore($folder)));
    }

    /**
     *
     * @return string
     */
    public function uninstall() : \Iterator
    {
        $idt = $this->getOutputIndentation();
        $transaction = $this->getWorkbench()->data()->startTransaction();
        
        yield $idt . 'Uninstalling ' . $this->getName() . ':' . PHP_EOL;
        
        $counter = 0;
        
        // Uninstall the main model now
        // Make sure to load all objects before starting to delete, so that their model
        // is available at delete time for cascading deletes, etc. If not done so, relations
        // might get broken because the data sources get removed before the objects and
        // the objects loose their base attributes. We've had broken self-relations because
        // an object inherited the UID from a data source base object, which was not present
        // anymore when the object was loaded.
        $dataSheets = $this->getModelSheets();
        $objects = [];
        foreach ($dataSheets as $sheet) {
            if ($sheet->getMetaObject()->is('exface.Core.APP') === true) {
                $appSheet = $sheet;
                $appSheet->dataRead();
                $counter += $appSheet->countRows();
            }
            if ($sheet->getMetaObject()->is('exface.Core.OBJECT') === true) {
                $objectSheet = $sheet;
                $objectSheet->dataRead();
                foreach ($objectSheet->getUidColumn()->getValues() as $objectUid) {
                    try {
                        $objects[] = MetaObjectFactory::createFromString($this->getWorkbench(), $objectUid);
                    } catch (\Throwable $e) {
                        $this->getWorkbench()->getLogger()->logException(new InstallerRuntimeError($this, 'Broken object to be uninstalled: ' . $objectUid, null, $e), LoggerInterface::WARNING);
                    }
                }
            }
        }
        // Delete all model sheets in reverse order
        $dataSheets = array_reverse($dataSheets);
        foreach ($dataSheets as $sheet) {
            if ($sheet->hasUIdColumn()) {
                // Read data to fill the UID column. Some data source do not support deletes via
                // filter (or filter over relations), so it is safer to fetch the UIDs here.
                $sheet->dataRead();
                if ($sheet->hasUidColumn(true)) {
                    $sheet->getFilters()->removeAll();
                    $counter += $sheet->dataDelete($transaction);
                }
            } else {
                $counter += $sheet->dataDelete($transaction);
            }
        }
        unset($objects);
        
        $transaction->commit();
        
        if ($counter === 0) {
            yield $idt.$idt . 'Nothing to do.' . PHP_EOL;
        } else {
            yield $idt.$idt . 'Removed ' . $this->getName() . ' successfully!' . PHP_EOL; 
        }
    }
    
    protected function setDataFolderPath(string $pathRelativeToApp) : DataInstaller
    {
        $this->path = $pathRelativeToApp;
        return $this;
    }
    
    protected function getDataFolderPathRelative() : string
    {
        return $this->path;
    }
    
    protected function getDataFolderPathAbsolute(string $basePathAbsolute) : string
    {
        return $basePathAbsolute . DIRECTORY_SEPARATOR . $this->getDataFolderPathRelative();
    }

    /**
     * Writes JSON File of a $data_sheet to a specific location
     *
     * @param string $modelDir            
     * @param DataSheetInterface $sheet
     * @param string $filename_prefix            
     * @return string[]
     */
    protected function exportModelFile(string $modelDir, DataSheetInterface $sheet, string $prevExportDir = null) : array
    {
        $obj = $sheet->getMetaObject();
        $objPath = $this->getModelFilePath($obj);
        $objPathPhs = StringDataType::findPlaceholders($objPath);
        $objPathPhColNames = [];
        $removeColNames = [];
        foreach ($objPathPhs as $attrAlias) {
            if (! $col = $sheet->getColumns()->getByExpression($attrAlias)) {
                $col = $sheet->getColumns()->addFromExpression($attrAlias);
                $removeColNames[] = $col->getName();
            }
            $objPathPhColNames[$attrAlias] = $col->getName();
        }
        
        $requiredCols = $sheet->getColumns()->getAll();
        $sheet->dataRead();
        // Reading data might add add some columns (e.g. if one of the "real" columns is a formula
        // with multiple other attributes), so we need to remove them here to avoid installing things
        // that were not intended to change. For example, exporting PAGE_GROUP_PAGES also means
        // including the LABEL column, which is a =Concatenate() with PAGE_GROUP__NAME. This adds
        // PAGE_GROUP__NAME to the data of PAGE_GROUP_PAGES. When performing dataReplace() this results
        // in an error.
        foreach ($sheet->getColumns() as $col) {
            if (! in_array($col, $requiredCols)) {
                $sheet->getColumns()->remove($col);
            }
        }
        
        if ($sheet->isEmpty()) {
            return [];
        }
        
        if (! file_exists($modelDir)) {
            Filemanager::pathConstruct($modelDir);
        }
        
        $result = [];
        $fileManager = $this->getWorkbench()->filemanager();
        $fileName = FilePathDataType::findFileName($objPath, true);
        /* @var $tsBehavior \exface\Core\Behaviors\TimeStampingBehavior */
        $excludeAttrs = [];
        foreach ($obj->getBehaviors()->getByPrototypeClass(TimeStampingBehavior::class) as $tsBehavior) {
            if ($tsBehavior->hasUpdatedByAttribute()) {
                $excludeAttrs[] = $tsBehavior->getUpdatedByAttribute();
            }
            if ($tsBehavior->hasUpdatedOnAttribute()) {
                $excludeAttrs[] = $tsBehavior->getUpdatedOnAttribute();
            }
        }
        if (! empty($objPathPhs)) {
            $rows = $sheet->getRows();
            $rowsByPath = [];
            foreach ($rows as $row) {
                $objPathPhVals = [];
                foreach ($objPathPhs as $ph) {
                    $phColName = $objPathPhColNames[$ph];
                    $objPathPhVals[$ph] = $row[$phColName] ?? null;
                    if (in_array($phColName, $removeColNames) === true) {
                        unset($row[$phColName]);
                    }
                }
                $rowPath = StringDataType::replacePlaceholders($objPath, $objPathPhVals);
                $rowsByPath[$rowPath][] = $row;
            }
            foreach ($removeColNames as $colname) {
                $sheet->getColumns()->removeByKey($colname);
            }
            
            $uxon = $sheet->exportUxonObject();
            foreach ($rowsByPath as $filePathRel => $filteredRows) {
                $filePathRel = trim(FilePathDataType::normalize($filePathRel, DIRECTORY_SEPARATOR));
                // Make sure the path has no white spaces to avoid issues when calling
                // CLI commands
                if (mb_stripos($filePathRel, ' ') !== false) {
                    $fileName = FilePathDataType::findFileName($filePathRel, true);
                    $folderPathRel = mb_substr($filePathRel, 0, (-1) * mb_strlen($fileName));
                    $folderPathRel = str_replace(' ', '::', subject: $folderPathRel);
                    $folderPathRel = StringDataType::convertCaseDelimiterToCamel($folderPathRel, '::', false);
                    $filePathRel = $folderPathRel . $fileName;
                }
                // Put only the filtered rows into the UXON. For now NO prettifying!!! Otherwise the
                // diff with the previous version below will produces false positives!
                $uxon->setProperty('rows', $filteredRows);
                $filePath = $modelDir . DIRECTORY_SEPARATOR . $filePathRel;
                $folderPath = FilePathDataType::findFolderPath($filePath);
                $result[] = $filePath;
                $prevPath = $prevExportDir . DIRECTORY_SEPARATOR . $filePathRel;
                $changesDetected = true;
                if (file_exists($prevPath)) {
                    $changesDetected = false;
                    $splitSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $uxon)->copy();
                    $decryptedSheet = $splitSheet->copy()->removeRows()->addRows($splitSheet->getRowsDecrypted());
                    foreach ($this->readModelSheetsFromFolders($prevPath) as $prevSheet) {
                        if ($decryptedSheet->countRows() !== $prevSheet->countRows()) {
                            $changesDetected = true;
                            break;
                        }
                        if (count($decryptedSheet->getRow(0)) !== count($prevSheet->getRow(0))) {
                            $changesDetected = true;
                            break;
                        }
                        $diff = $decryptedSheet->getRowsDiff($prevSheet, $excludeAttrs);
                        if (! empty($diff)) {
                            $changesDetected = true;
                            break;
                        }
                    }
                }
                if ($changesDetected === false) {
                    // If there were no changes, simply copy the previous file back to the
                    // current model folder
                    if (! is_dir($folderPath)) {
                        $fileManager::pathConstruct($folderPath);
                    }
                    rename($prevPath, $filePath);
                    $result[array_key_last($result)] .= ' - no change';
                    continue;
                } else {
                    // For changes prettify the rows and dump the new JSON
                    $uxon->setProperty('rows', $this->exportModelRowsPrettified($sheet, $filteredRows));
                    $result[array_key_last($result)] .= ' - changed ' . $sheet->countRows();
                    $fileManager->dumpFile($filePath, $uxon->toJson(true));
                }
            }
        } else {
            $filePath = $modelDir . DIRECTORY_SEPARATOR . $fileName;
            $result[] = $filePath;
            $prevPath = $prevExportDir . DIRECTORY_SEPARATOR . $fileName;
            if (file_exists($prevPath)) {
                $decryptedSheet = $sheet->copy()->removeRows()->addRows($sheet->getRowsDecrypted());
                $changesDetected = false;
                foreach ($this->readModelSheetsFromFolders($prevPath) as $prevSheet) {
                    if ($decryptedSheet->countRows() !== $prevSheet->countRows()) {
                        $changesDetected = true;
                        break;
                    }
                    if (count($decryptedSheet->getRow(0)) !== count($prevSheet->getRow(0))) {
                        $changesDetected = true;
                        break;
                    }
                    $diff = $decryptedSheet->getRowsDiff($prevSheet, $excludeAttrs);
                    if (! empty($diff)) {
                        $changesDetected = true;
                        break;
                    }
                }
                if ($changesDetected === false) {
                    rename($prevPath, $filePath);
                    $result[0] .= ' - no change';
                    return $result;
                }
            }
            $uxon = $sheet->exportUxonObject();
            $uxon->setProperty('rows', $this->exportModelRowsPrettified($sheet));
            $result[0] .= ' - changed ' . $sheet->countRows();
            $contents = $uxon->toJson(true);
            $fileManager->dumpFile($filePath, $contents);
        }
        
        return $result;
    }
    
    protected function exportModelRowsPrettified(DataSheetInterface $sheet, array $rows = null) : array
    {
        $rows = $rows ?? $sheet->getRowsDecrypted();
        foreach ($sheet->getColumns() as $col) {
            $dataType = $col->getDataType();
            $colName = $col->getName();
            switch (true) {
                case $dataType instanceof EncryptedDataType:
                    $salt = $this->getAppSalt();
                    foreach ($rows as $i => $row) {
                        $val = $row[$colName];
                        if ($val !== null && $val !== '') {
                            $valEncrypted = EncryptedDataType::encrypt($salt, $val, EncryptedDataType::ENCRYPTION_PREFIX_DEFAULT);
                            $rows[$i][$colName] = $valEncrypted;
                        }
                    }
                    break;
                case $dataType instanceof DateDataType:
                    foreach ($rows as $i => $row) {
                        $val = $row[$colName];
                        if ($val !== null && $val !== '') {
                            $rows[$i][$colName] = $dataType->cast($val);
                        }
                    }
                    break;
                case $dataType instanceof JsonDataType:
                    foreach ($rows as $i => $row) {
                        $val = $row[$colName];
                        if ($val !== null && $val !== '') {
                            try {
                                $valArray = UxonObject::fromAnything($val)->toArray(); 
                                $valArray = $this->textToArrayRecursive($valArray);
                                $rows[$i][$colName] = $valArray;
                            } catch (\Throwable $e) {
                                // Ignore errors
                            }
                        }
                    }
                    break;
                case $dataType instanceof TextDataType:
                    foreach ($rows as $i => $row) {
                        $val = $row[$colName];
                        if ($val !== null && $val !== '' && is_string($val)) {
                            try {
                                $lines = $this->textToArray($val);
                                if (is_array($lines)) {
                                    $rows[$i][$colName] = $lines;
                                }
                            } catch (\Throwable $e) {
                                // Ignore errors
                            }
                        }
                    }
                    break;
            }
        }
        return $rows;
    }
    
    protected function filterRows(array $rows, string $filterRowName, string $filterRowValue)
    {
        $filter = new RowDataArrayFilter();
        $filter->addAnd($filterRowName, $filterRowValue, ComparatorDataType::EQUALS);
        return $filter->filter($rows);
    }
    
    /**
     * Add an object to be exported with the app model replacing all rows on a target system when deploying
     *
     * @param string $objectSelector
     * @param string $sorterAttribute
     * @param string $appRelationAttribute
     * @param string[] $excludeAttributeAliases
     * @param string $filePath
     * @return DataInstaller
     */
    public function addDataToReplace(string $objectSelector, string $sorterAttribute, string $appRelationAttribute, array $excludeAttributeAliases = [], string $filePath = null) : DataInstaller
    {
        $this->addDataOfObject($objectSelector, $sorterAttribute, $appRelationAttribute, $excludeAttributeAliases, $filePath);
        return $this;
    }
    
    /**
     * Add an object to be exported with the app model replacing only rows with matching UIDs on a target system when deploying
     *
     * @param string $objectSelector
     * @param string $sorterAttribute
     * @param string $appRelationAttribute
     * @param string[] $excludeAttributeAliases
     * @param string $filePath
     * @return DataInstaller
     */
    public function addDataToMerge(string $objectSelector, string $sorterAttribute, string $appRelationAttribute = null, array $excludeAttributeAliases = [], string $filePath = null) : DataInstaller
    {
        $this->addDataOfObject($objectSelector, $sorterAttribute, $appRelationAttribute, $excludeAttributeAliases, $filePath);
        return $this;
    }
    
    /**
     * 
     * @param string $objectSelector
     * @param string $sorterAttribute
     * @param string $appRelationAttribute
     * @param string[] $excludeAttributeAliases
     * @param string $filePath
     * @return DataSheetInterface
     */
    protected function addDataOfObject(string $objectSelector, string $sorterAttribute, string $appRelationAttribute = null, array $excludeAttributeAliases = [], string $filePath = null) : DataInstaller
    {
        $this->dataDefs[$objectSelector] = [
            'sorter' => $sorterAttribute,
            'app_relation' => $appRelationAttribute,
            'exclude' => $excludeAttributeAliases,
            'path' => $filePath
        ];
        return $this;
    }
    
    /**
     * 
     * @param string $objectSelector
     * @param string $sorterAttribute
     * @param string $appRelationAttribute
     * @param string[] $excludeAttributeAliases
     * @return DataSheetInterface
     */
    protected function createModelSheet(string $objectSelector, string $sorterAttribute, string $appRelationAttribute = null, array $excludeAttributeAliases = []) : DataSheetInterface
    {
        $cacheKey = $objectSelector . '::' . ($appRelationAttribute ?? '') . '::' . $sorterAttribute . '::' . implode(',', $excludeAttributeAliases);
        if (null === $ds = $this->dataSheets[$cacheKey] ?? null) {
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objectSelector);
            $ds->getSorters()->addFromString($sorterAttribute, SortingDirectionsDataType::ASC);
            if ($ds->getMetaObject()->hasUidAttribute()) {
                $ds->getSorters()->addFromString($ds->getMetaObject()->getUidAttributeAlias(), SortingDirectionsDataType::ASC);
            }

            // Add a fake column atop of every row that will tell humans looking at the JSON what this
            // row was originally
            if ($ds->getMetaObject()->hasLabelAttribute()) {
                $ds->getColumns()->addFromExpression($ds->getMetaObject()->getLabelAttributeAlias(), '_EXPORT_SUMMARY', true);
            }
                
            foreach ($ds->getMetaObject()->getAttributeGroup('~WRITABLE')->getAttributes() as $attr) {
                if (in_array($attr->getAlias(), $excludeAttributeAliases)){
                    continue;
                }
                $ds->getColumns()->addFromExpression($attr->getAlias());
            }
            
            try {
                $appUid = $this->getApp()->getUid();
            } catch (AppNotFoundError $e) {
                $appUid = null;
            }
            
            // It is very important to filter over app UID - otherwise we might uninstall EVERYTHING
            // when uninstalling an app, that is broken (this actually happened!).
            // Also make sure to cache the sheet in case we are uninstalling and
            // we will need the sheet again after its model was removed.
            switch (true) {
                // If there is not app relation, don't filter (nothing to filter over), but cache the sheet
                case $appRelationAttribute === null:
                    $this->dataSheets[$cacheKey] = $ds;
                    break;
                    // If we know the UID at this moment, add a filter over the relation to the app
                case $appUid !== null:
                    // Use IS and not EQUALS here for historical reasons. If changed to EQUALS
                    // ALL existing model files will change!
                    $ds->getFilters()->addConditionFromString($appRelationAttribute, $appUid, ComparatorDataType::IS);
                    $this->dataSheets[$cacheKey] = $ds;
                    break;
                    // If we don't konw the UID, do not cache the sheet - maybe the UID will be already
                    // there next time (e.g. if we need the sheet after the app was installed)
                default:
                    // If we do not have an app UID, make sure the filter NEVER matches anything, so the
                    // installer will not have any effect!
                    $ds->getFilters()->addConditionFromString($appRelationAttribute, '0x0', ComparatorDataType::EQUALS);
            }
        }
        
        return $ds->copy();
    }
    
    /**
     * 
     * @return DataSheetInterface[]
     */
    protected function getModelSheets() : array
    {        
        $sheets = array();
        foreach ($this->dataDefs as $objAlias => $def) {
            $sheets[] = $this->createModelSheet($objAlias, $def['sorter'], $def['app_relation'], $def['exclude']);
        }
        return $sheets;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @throws InstallerRuntimeError
     * @return string
     */
    protected function getModelFilePath(MetaObjectInterface $object) : string
    {
        $objAlias = $object->getAliasWithNamespace();
        $def = $this->dataDefs[$objAlias] ?? null;
        if ($def === null) {
            throw new InstallerRuntimeError($this, 'Cannot determine file path for object ' . $object->__toString() . ': no installer configuration found!');
        }
        $path = $def['path'] ?? null;
        if ($path === null) {
            $defIdx = $this->getModelFileIndex($objAlias) ?? 0;
            $prefix = str_pad($defIdx + $this->filenameIndexStart, 2, '0', STR_PAD_LEFT) . '_';
            $path = $prefix . $object->getAlias() . '.json';
        }
        return $path;
    }
    
    /**
     * 
     * @param string $objectAlias
     * @return int|NULL
     */
    protected function getModelFileIndex(string $objectAlias) : ?int
    {
        $idx = array_search($objectAlias, array_keys($this->dataDefs));
        if ($idx === false) {
            $idx = null;
        }
        return $idx;
    }
    
    /**
     * Generates data sheets from the Model folder.
     * 
     * It is important, that this is a generator as there are sheets for things like page groups,
     * where the meta object may not even exist at the time of reading - the objects and attributes
     * sheets must be read and processed first to read the other sheets.
     * 
     * @param string $absolutePath
     * @return DataSheetInterface[]
     */
    protected function readModelSheetsFromFolders($absolutePath) : \Generator
    {
        $uxons = [];
        $folderSheetUxons = $this->readDataSheetUxonsFromFolder($absolutePath);
        
        // Sort by leading numbers in the file names accross all folders
        ksort($folderSheetUxons);
        
        // Organize the UXON objects in an array like [object_alias => [uxon1, uxon2, ...]]
        foreach ($folderSheetUxons as $key => $uxon) {
            $type = StringDataType::substringBefore($key, '@');
            $uxons[$type][] = $uxon;
        }
        
        // For each object, combine it's UXONs into a single data sheet
        foreach ($uxons as $key => $array) {
            $cnt = count($array);
            // Init the data sheet from the first UXON, but without any rows. We will preprocess
            // the rows later and transform expanded UXON values into strings.
            $baseUxon = $array[0];
            
            $objAlias = $baseUxon->getProperty('object_alias');
            if ($objAlias === null || ! $this->isInstallableObject($objAlias)) {
                $this->getWorkbench()->getLogger()->warning('Skipping model sheet "' . $key . '": object not known to this installer!');
                continue;
            }
            
            // Save the rows for later processing
            $rows = $baseUxon->getProperty('rows')->toArray();
            $baseUxon->unsetProperty('rows');
            $baseSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $baseUxon);
            $baseColCount = $baseSheet->getColumns()->count();
            // Add rows from all the other UXONs
            if ($cnt > 1) {
                for ($i = 1; $i < $cnt; $i++) {
                    $partUxon = $array[$i];
                    $partRows = $partUxon->hasProperty('rows') ? $partUxon->getProperty('rows')->toArray() : [];
                    $partUxon->unsetProperty('rows');
                    // Instantiate an empty data sheet to check, if it's compatible!
                    $sheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $partUxon);
                    if (! $baseSheet->getMetaObject()->isExactly($sheet->getMetaObject())) {
                        throw new InstallerRuntimeError($this, 'Model sheet type mismatch: model sheets with same name must have the same structure in all subfolders of the model!');
                    }
                    if ($sheet->getColumns()->count() !== $baseColCount) {
                        throw new InstallerRuntimeError($this, 'Corrupted model data: all model sheets of the same type must have the same columns!');
                    }
                    $rows = array_merge($rows, $partRows);
                }
            }
            
            // Preprocess row values
            foreach ($baseSheet->getColumns() as $col) {
                // UXON values are normally transformed into JSON when exporting the model to
                // increase readability of diffs. Need to transform them back to strings here.
                // The check for JsonDataType is a fix upgrading older installations where the
                // UxonDataType was not a PHP class yet.
                $dataType = $col->getDataType();
                $colName = $col->getName();
                switch (true) {
                    case $dataType instanceof EncryptedDataType:
                        foreach ($rows as $i => $row) {
                            $val = $row[$colName];
                            if (is_string($val) && StringDataType::startsWith($val, EncryptedDataType::ENCRYPTION_PREFIX_DEFAULT)) {
                                $salt = $this->getAppSalt();
                                $valDecrypt = EncryptedDataType::decrypt($salt, $val, EncryptedDataType::ENCRYPTION_PREFIX_DEFAULT);
                                $rows[$i][$colName] = $val = $valDecrypt;
                            }
                            if (($dataType->getInnerDataType() instanceof JsonDataType) && is_array($val)) {
                                $rows[$i][$colName] = (UxonObject::fromArray($val))->toJson();
                            }
                        }
                        break;
                    case $dataType instanceof JsonDataType:
                        foreach ($rows as $i => $row) {
                            $val = $row[$colName];
                            if (is_array($val)) {
                                $val = $this->textFromArrayRecursive($val);
                                $rows[$i][$colName] = (UxonObject::fromArray($val))->toJson();
                            }
                        }
                        break;
                    // Long texts are exported as arrays, so glue them all together here
                    case $dataType instanceof TextDataType:
                        foreach ($rows as $i => $row) {
                            $val = $row[$colName];
                            if (is_array($val)) {
                                $val = $this->textFromArray($val);
                                if (is_string($val)) {
                                    $rows[$i][$colName] = $val;
                                }
                            }
                        }
                        break;
                }
            }
            
            // Add all the rows to the sheet.
            $baseSheet->addRows($rows, false, false);
            
            $baseSheet = $this->applyCompatibilityFixesToDataSheet($baseSheet);
            
            yield $baseSheet;
        }
    }
    
    protected function getAppSalt() : string
    {
        if ($this->salt) {
            return $this->salt;
        }
        $config = ConfigurationFactory::create($this->getWorkbench());
        $config->loadConfigFile($this->getWorkbench()->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR . self::ENCRYPTION_CONFIG_FILE);
        if ($config->hasOption($this->getApp()->getAliasWithNamespace())) {
            $salt = base64_encode($config->getOption($this->getApp()->getAliasWithNamespace()));
            $this->salt = $salt;
            return $salt;
        }
        if ($this->getApp()->isInstalled()) {
            $uid = $this->getApp()->getUid();
        } else {
            $filePath = $this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'composer.json';
            if (file_exists($filePath)) {
                $json = json_decode(file_get_contents($filePath), true);
            } else {
                $json = [];
            }
            $uid = $json['extra']['app']['app_uid'] ?? null;
        }
        if (! $uid) {
            throw new EncryptionError("No encryption/decryption salt can be created for the app '{$this->getApp()->getAliasWithNamespace()}' !");
        }
        $salt = EncryptedDataType::createSaltFromString(substr($uid, 2,32));
        $this->salt = $salt;
        return $this->salt;
    }
    
    /**
     * Returns an array of UXON objects with `<filename>@<abs_path>` for keys
     * 
     * These keys allow to easily sort the entire array by filename
     * 
     * @param string $absolutePath
     * @return UxonObject[]
     */
    protected function readDataSheetUxonsFromFolder(string $absolutePath) : array
    {
        $folderUxons = [];
        
        if (is_file($absolutePath)) {
            $dataSheet = $this->readDataSheetUxonFromFile($absolutePath);
            $folderUxons[FilePathDataType::findFileName($absolutePath) . '@' . FilePathDataType::findFolderPath($absolutePath)] = $dataSheet;
            return $folderUxons;
        }
        
        foreach (scandir($absolutePath) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $path = $absolutePath . DIRECTORY_SEPARATOR . $file;
            $key = $file . '@' . $absolutePath;
            if (is_dir($path)) {
                $folderUxons = array_merge($folderUxons, $this->readDataSheetUxonsFromFolder($path));
            } else {
                $folderUxons[$key] = $this->readDataSheetUxonFromFile($path);
            }
        }
        
        return $folderUxons;
    }
    
    /**
     * 
     * @param string $path
     * @return UxonObject
     */
    protected function readDataSheetUxonFromFile(string $path) : UxonObject
    {
        $contents = file_get_contents($path);
        $contents = $this->applyCompatibilityFixesToFileContent($path, $contents);
        return UxonObject::fromJson($contents);
    }
    
    /**
     * 
     * @param string $path
     * @param string $contents
     * @return string
     */
    protected function applyCompatibilityFixesToFileContent(string $path, string $contents) : string
    {      
        return $contents;
    }
    
    /**
     * 
     * @param DataSheetInterface $sheet
     * @return DataSheetInterface
     */
    protected function applyCompatibilityFixesToDataSheet(DataSheetInterface $sheet) : DataSheetInterface
    {      
        return $sheet;
    }
    
    /**
     * 
     * @param ConditionGroupInterface $condition_group
     * @return bool
     */
    protected function checkFiltersMatchModel(ConditionGroupInterface $condition_group) : bool
    {
        foreach ($condition_group->getConditions() as $condition){
            if(! $condition->getExpression()->isMetaAttribute()){
                return false;
            }
        }
        
        foreach ($condition_group->getNestedGroups() as $subgroup){
            if (! $this->checkFiltersMatchModel($subgroup)){
                return false;
            }
        }
        return true;
    }
    
    /**
     * Returns TRUE if this installer was disabled in the configuration of the installed app.
     *
     * @return bool
     */
    protected function isDisabled() : bool
    {
        return $this->getConfigOption(static::CONFIG_OPTION_DISABLED) ?? false;
    }
    
    /**
     * Returns the value of a configuration option - one of the CONFIG_OPTION_xxx constants.
     *
     * This method automatically uses the config option prefix (namespace) of this
     * installer.
     *
     * @param string $name
     * @return string
     */
    protected function getConfigOption(string $name) : ?string
    {
        $config = $this->getApp()->getConfig();
        $option = static::CONFIG_OPTION_PREFIX . '.' . mb_strtoupper($this->getClassName()) . '.' . $name;
        if ($config->hasOption($option)) {
            return $config->getOption($option);
        } else {
            return null;
        }
    }
    
    /**
     * Returns the short name of the class, means name without namespace.
     * 
     * @return string
     */
    protected function getClassName() : string
    {
        if ($this->className === null) {
            $refl = new \ReflectionClass($this);
            $this->className = $refl->getShortName();
        }
        return $this->className;
    }

    /**
     * Splits a text into a per-line array if there are multiple lines or 
     * returns it as-is if it has one line only
     * 
     * @param string $text
     * @return string|array
     */
    protected function textToArray(string $text) : string|array
    {
        $lineBreaks = StringDataType::findLineBreakChars($text);
        if (empty($lineBreaks)) {
            return $text;
        }
        $lines = StringDataType::splitLines($text);
        array_unshift($lines, self::SPLIT_TEXT_COMMENT . '`' . $lineBreaks[0] . '`');
        return $lines;
    }

    /**
     * Splits all texts inside the given array into per-line arrays recursively
     * 
     * @param array $array
     * @return array
     */
    protected function textToArrayRecursive(array $array) : array
    {
        foreach ($array as $key => $val) {
            switch (true) {
                case is_array($val):
                    $array[$key] = $this->textToArrayRecursive($val);
                    break;
                case is_string($val) && $val !== '':
                    $lines = $this->textToArray($val);
                    if (! empty(StringDataType::findLineBreakChars($val))) {
                        $br = 1;
                    }
                    if (is_array($lines)) {
                        $array[$key] = $lines;
                    }
                    break;
            }
        }
        return $array;
    }

    /**
     * Checks if the given array originally was a per-line split and merges it back together.
     * 
     * Returns a string if the array was a multiline string originally
     * 
     * @param array $lines
     * @return string|array
     */
    protected function textFromArray(array $lines) : string|array
    {
        if (! is_string($lines[0]) || strpos($lines[0], self::SPLIT_TEXT_COMMENT) !== 0) {
            return $lines;
        }
        $comment = array_shift($lines);
        $lineBreak = StringDataType::substringAfter($comment, self::SPLIT_TEXT_COMMENT);
        $lineBreak = trim(trim($lineBreak), "`");
        return implode($lineBreak, $lines);
    }

    /**
     * Restores all values in the given array if they are per-line splits
     * 
     * @param array $array
     * @return array
     */
    protected function textFromArrayRecursive(array $array) : array
    {
        foreach ($array as $key => $val) {
            if (! is_array($val)) {
                continue;
            }
            $text = $this->textFromArray($val);
            if (is_string($text)) {
                $array[$key] = $text;
            } else {
                $array[$key] = $this->textFromArrayRecursive($val);
            }
        }
        return $array;
    }
}