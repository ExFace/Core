<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Behaviors\TimeStampingBehavior;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
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

/**
 * Saves all model entities and eventual custom added data as JSON files in the `Model` subfolder of the app.
 * 
 * ## Export folder and file structure
 * 
 * The folder structure is outlined below. Each file is a data sheet UXON containing
 * all the model entities of it's type except for pages. Since the page installer logic
 * is more complex than simple replacement (and also for historical reasons), each page 
 * is stored in a separate file as it's UXON-export.
 * 
 * - app.alias.object_alias_1 <- object-bound model entities are saved in subfolder per object
 *      - 02_OBJECT.json
 *      - 03_OBJECT_BEHAVIORS.json
 *      - 04_ATTRIBUTE.json
 *      - 08_OBJECT_ACTION.json
 * - app.alias.object_alias_2
 * - ...
 * - 00_APP.json <- entities without an object binding are stored as data sheet UXON
 * - 01_DATATYPE.json
 * - ...
 * - 99_PAGES
 *      - app.alias.page_alias_1.json
 *      - app.alias.page_alias_2.json
 *      - ... 
 * 
 * In contrast to the regular UXON-export of a data sheet where the value of each column is
 * stored as a string, the model sheet columns containing UXON have prettyprinted JSON values.
 * This makes it easier to identify changes in larger UXON objects like default editors, aciton
 * configurations, etc.
 * 
 * Object-bound entities like attributes, behaviors, etc. are saved per object in subfolders.
 * This simplifies change management greatly as it is difficult to diff large JSON files properly.
 * 
 * When installing, the files are processed in alphabetical order - more precisely in the order
 * of the numerc filename prefixes. For each entity type a data sheet is instantiated and 
 * `DataSheetInterface::dataReplaceByFilters()` is preformed filtered by the app - this makes
 * sure all possibly existing entities bound to this app are completely replaced by the contents
 * of the data sheet.
 * 
 * Object-bound entities are first collected into a single large data sheet to make sure all
 * data is replaced at once.
 * 
 * Pages are installed last by calling the dedicated `PageInstaller`.
 * 
 * ## Encryption
 * Every content of a attribute with `EncryptedDataType` as data type will be exported as an encrypted string.
 * The used encryption salt will either be build from the app uid or you can provide a custom salt.
 * The custom salt has to be placed in the `Encryption.config.json` file in the `config` folder with the app alias (with namespace) as key.
 * The salt has to be 32 characters long. When importing the metamodell on a different PowerUi installation you will also need that config
 * file with that key you used for encryption.
 * You can use the followign website to create a salt:
 * `http://www.unit-conversion.info/texttools/random-string-generator/`
 * CAREFUL: If you lose the used custom salt for encryption during the export you will not be able to restore the encrypted
 * data and the affected data will be lost.
 * 
 * ## Behaviors
 * 
 * NOTE: The `TimeStampingBehavior` of the model objects is disabled before install, so the
 * create/update stamps of the exported model are saved correctly.
 * 
 * ## Backwards compatibilty issues
 * 
 * Keep in mind, that the metamodel installer requires the current model in the DB to be
 * compatible with the model files it installs. In other words, if the exported attribute-sheet
 * has certain columns, the currently deployed metamodel for attributes themselves MUST have
 * attributes for each of these columns. The same goes for any other metamodel entity.
 * 
 * Thus, when attributes of model entities change, the compatibility between files and current
 * model must be restored at the time of installation. Here is a typical approach:
 * 
 * - Changes to the model (e.g. adding new attributes to objects, attributes, etc.) need to be applied 
 * in the core SQL migrations to make sure they are already there when loading this installer.
 * - Legacy model files (exported from older models) may need some transformation. This can be
 * applied in `applyCompatibilityFixesToFileContent()` or `applyCompatibilityFixesToDataSheet()`
 * depending on where the changes are easier to implement.
 * 
 * @author Andrej Kabachnik
 *
 */
class MetaModelInstaller extends DataInstaller
{
    const FOLDER_NAME_MODEL = 'Model';
    
    const FOLDER_NAME_PAGES = '99_PAGE';
    
    private $objectSheet = null;
    
    private $salt = null;
    
    public function __construct(SelectorInterface $selectorToInstall)
    {
        parent::__construct($selectorToInstall);
        
        $this->setDataFolderPath(self::FOLDER_NAME_MODEL);
        
        $this->addDataOfObject('exface.Core.APP', 'CREATED_ON', 'UID', ['PUPLISHED']);
        $this->addDataOfObject('exface.Core.DATATYPE', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.OBJECT', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.OBJECT_BEHAVIORS', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.ATTRIBUTE', 'CREATED_ON', 'OBJECT__APP');
        $this->addDataOfObject('exface.Core.DATASRC', 'CREATED_ON', 'APP', [
            'CONNECTION',
            'CUSTOM_CONNECTION',
            'QUERYBUILDER',
            'CUSTOM_QUERY_BUILDER'
        ]);
        $this->addDataOfObject('exface.Core.CONNECTION', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.MESSAGE', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.OBJECT_ACTION', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.UXON_PRESET', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.PAGE_TEMPLATE', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.ATTRIBUTE_COMPOUND', 'CREATED_ON', 'COMPOUND_ATTRIBUTE__OBJECT__APP');
        $this->addDataOfObject('exface.Core.PAGE_GROUP', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.PAGE_GROUP_PAGES', 'CREATED_ON', 'PAGE__APP');
        $this->addDataOfObject('exface.Core.USER_ROLE', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.AUTHORIZATION_POINT', 'CREATED_ON', 'APP', [
            'DEFAULT_EFFECT',
            'DEFAULT_EFFECT_LOCAL',
            'COMBINING_ALGORITHM',
            'COMBINING_ALGORITHM_LOCAL',
            'DISABLED_FLAG'
        ]);
        $this->addDataOfObject('exface.Core.AUTHORIZATION_POLICY', 'CREATED_ON', 'APP', [
            'DISABLED_FLAG'
        ]);
        $this->addDataOfObject('exface.Core.QUEUE', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.SCHEDULER', 'CREATED_ON', 'APP', [
            'LAST_RUN'
        ]);
        $this->addDataOfObject('exface.Core.COMMUNICATION_CHANNEL', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.COMMUNICATION_TEMPLATE', 'CREATED_ON', 'APP');
    }

    /**
     *
     * @param string $source_absolute_path
     * @return string
     */
    public function install(string $source_absolute_path) : \Iterator 
    {
        yield from $this->installModel($this->getSelectorInstalling(), $source_absolute_path);
    }

    /**
     *
     * @param string $destination_absolute_path
     *            Destination folder for meta model backup
     * @return string
     */
    public function backup(string $destination_absolute_path) : \Iterator
    {
        yield from $this->backupModel($destination_absolute_path);
    }

    /**
     *
     * @return string
     */
    public function uninstall() : \Iterator
    {
        $idt = $this->getOutputIndentation();
        $transaction = $this->getWorkbench()->data()->startTransaction();
        
        $pageInstaller = $this->getPageInstaller();
        $pageInstaller->setOutputIndentation($idt);
        $pageInstaller->setTransaction($transaction);
        yield from $pageInstaller->uninstall();
        
        yield $idt . 'Uninstalling model:' . PHP_EOL;
        
        $counter = 0;
        
        // Uninstall the main model now
        // Make sure to load all objects before starting to delete, so that their model
        // is available at delete time for cascading deletes, etc. If not done so, relations
        // might get broken because the data sources get removed before the objects and
        // the objects loose their base attributes. We've had broken self-relations because
        // an object inherited the UID from a data source base object, which was not present
        // anymore when the object was loaded.
        $modelSheets = $this->getModelSheets();
        $objects = [];
        foreach ($modelSheets as $sheet) {
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
        $modelSheets = array_reverse($modelSheets);
        foreach ($modelSheets as $sheet) {
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
            yield $idt.$idt . 'Removed app model successfully!' . PHP_EOL; 
        }
    }

    /**
     * Analyzes model data sheet and writes json files to the model folder
     *
     * @param string $destinationAbsolutePath
     * @return string
     */
    protected function backupModel($destinationAbsolutePath) : \Iterator
    {
        $idt = $this->getOutputIndentation();
        $app = $this->getApp();
        $dir = $this->getDataFolderPathAbsolute($destinationAbsolutePath);
        
        // Remove any old files AFTER the data sheets were read successfully
        // in order to keep old data on errors.
        $dirOld = null;
        if (is_dir($dir)) {
            $dirOld = $dir . '.tmp';
            rename($dir, $dirOld);
        }
        
        // Make sure, the destination folder is there and empty (to remove 
        // files, that are not neccessary anymore)
        $app->getWorkbench()->filemanager()->pathConstruct($dir);
        
        // Save each data sheet as a file and additionally compute the modification date of the last modified model instance and
        // the MD5-hash of the entire model definition (concatennated contents of all files). This data will be stored in the composer.json
        // and used in the installation process of the package
        foreach ($this->getModelSheets() as $nr => $ds) {
            $ds->dataRead();
            $this->exportModelFile($dir, $ds, str_pad($nr, 2, '0', STR_PAD_LEFT) . '_', true, $dirOld);
        }
        
        // Save some information about the package in the extras of composer.json
        $package_props = array(
            'app_uid' => $app->getUid(),
            'app_alias' => $app->getAliasWithNamespace()
        );
        
        $packageManager = $this->getWorkbench()->getApp("axenox.PackageManager");
        $composer_json = $packageManager->getComposerJson($app);
        $composer_json['extra']['app'] = $package_props;
        $packageManager->setComposerJson($app, $composer_json);
        
        yield $idt . 'Created meta model backup for "' . $app->getAliasWithNamespace() . '".' . PHP_EOL;
        
        // Backup pages.
        $pageInstaller = $this->getPageInstaller();
        $pageInstaller->setOutputIndentation($idt);
        yield from $pageInstaller->backup($destinationAbsolutePath);
        
        // Remove remaining old files
        if ($dirOld !== null) {
            Filemanager::deleteDir($dirOld);
        }
    }

    /**
     * Writes JSON File of a $data_sheet to a specific location
     *
     * @param string $modelDir            
     * @param DataSheetInterface $data_sheet
     * @param string $filename_prefix            
     * @return string[]
     */
    protected function exportModelFile(string $modelDir, DataSheetInterface $data_sheet, $filename_prefix = null, $split_by_object = true, string $prevExportDir = null) : array
    {
        if ($data_sheet->isEmpty()) {
            return [];
        }
        
        if (! file_exists($modelDir)) {
            Filemanager::pathConstruct($modelDir);
        }
        
        if ($split_by_object === true) {
            $objectUids = [];
            switch (true) {
                case $data_sheet->getMetaObject()->isExactly('exface.Core.OBJECT'): 
                    $col = $data_sheet->getUidColumn();
                    $objectUids = $col->getValues(false);
                    break;
                case $data_sheet->getMetaObject()->isExactly('exface.Core.ATTRIBUTE_COMPOUND'):
                    $col = $data_sheet->getColumns()->addFromExpression('COMPOUND_ATTRIBUTE__OBJECT');
                    $removeObjectCol = true;
                    $data_sheet->dataRead();
                    $objectUids = array_unique($col->getValues(false));
                    break;
                default: 
                    foreach ($data_sheet->getColumns() as $col) {
                        if ($attr = $col->getAttribute()) {
                            if ($attr->isRelation() && $attr->getRelation()->getRightObject()->isExactly('exface.Core.OBJECT') && $attr->isRequired()) {
                                $objectUids = array_unique($col->getValues(false));
                                break;
                            }
                        }
                    }
            }
        }
        $result = [];
        $fileManager = $this->getWorkbench()->filemanager();
        $fileName = $filename_prefix . $data_sheet->getMetaObject()->getAlias() . '.json';
        /* @var $tsBehavior \exface\Core\Behaviors\TimeStampingBehavior */
        $excludeAttrs = [];
        foreach ($data_sheet->getMetaObject()->getBehaviors()->getByPrototypeClass(TimeStampingBehavior::class) as $tsBehavior) {
            if ($tsBehavior->hasUpdatedByAttribute()) {
                $excludeAttrs[] = $tsBehavior->getUpdatedByAttribute();
            }
            if ($tsBehavior->hasUpdatedOnAttribute()) {
                $excludeAttrs[] = $tsBehavior->getUpdatedOnAttribute();
            }
        }
        if ($split_by_object && ! empty($objectUids)) {
            $rows = $data_sheet->getRows();
            if ($removeObjectCol === true) {
                $data_sheet->getColumns()->remove($col);
            }
            $uxon = $data_sheet->exportUxonObject();
            $objectColumnName = $col->getName();
            foreach ($objectUids as $objectUid) {
                $filteredRows = array_values($this->filterRows($rows, $objectColumnName, $objectUid));
                if ($removeObjectCol === true) {
                    for ($i = 0; $i < count($filteredRows); $i++) {
                        unset($filteredRows[$i][$objectColumnName]);
                    }
                }
                $uxon->setProperty('rows', $this->exportModelRowsPrettified($data_sheet, $filteredRows));
                $subfolder = $this->getObjectSubfolder($objectUid);
                $path = $modelDir . DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR . $fileName;
                $result[] = $path;
                $prevPath = $prevExportDir . DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR . $fileName;
                if (file_exists($prevPath)) {
                    $splitSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $uxon)->copy();
                    $decryptedSheet = $splitSheet->copy()->removeRows()->addRows($splitSheet->getRowsDecrypted());
                    $changesDetected = false;
                    foreach ($this->readModelSheetsFromFolders($prevPath) as $prevSheet) {
                        if ($decryptedSheet->countRows() !== $prevSheet->countRows()) {
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
                        if (! is_dir($modelDir . DIRECTORY_SEPARATOR . $subfolder));
                        mkdir($modelDir . DIRECTORY_SEPARATOR . $subfolder);
                        rename($prevPath, $path);
                        continue;
                    }
                }
                $fileManager->dumpFile($path, $uxon->toJson(true));
            }
        } else {
            $path = $modelDir . DIRECTORY_SEPARATOR . $fileName;
            $result[] = $path;
            $prevPath = $prevExportDir . DIRECTORY_SEPARATOR . $fileName;
            if (file_exists($prevPath)) {
                $decryptedSheet = $data_sheet->copy()->removeRows()->addRows($data_sheet->getRowsDecrypted());
                $changesDetected = false;
                foreach ($this->readModelSheetsFromFolders($prevPath) as $prevSheet) {
                    if ($decryptedSheet->countRows() !== $prevSheet->countRows()) {
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
                    rename($prevPath, $path);
                    return $result;
                }
            }
            $uxon = $data_sheet->exportUxonObject();
            $uxon->setProperty('rows', $this->exportModelRowsPrettified($data_sheet));
            $contents = $uxon->toJson(true);
            $fileManager->dumpFile($path, $contents);
        }
        
        return $result;
    }
    
    protected function exportModelRowsPrettified(DataSheetInterface $sheet, array $rows = null) : array
    {
        $rows = $rows ?? $sheet->getRowsDecrypted();
        foreach ($sheet->getColumns() as $col) {
            $dataType = $col->getDataType();
            switch (true) {
                case $dataType instanceof EncryptedDataType:
                    $colName = $col->getName();
                    foreach ($rows as $i => $row) {
                        $val = $row[$colName];
                        if ($val !== null && $val !== '') {
                            $salt = $this->getAppSalt();
                            $valEncrypted = EncryptedDataType::encrypt($salt, $val, EncryptedDataType::ENCRYPTION_PREFIX_DEFAULT);
                            $rows[$i][$colName] = $valEncrypted;
                        }
                    }
                    break;
                case $dataType instanceof JsonDataType:
                    $colName = $col->getName();
                    foreach ($rows as $i => $row) {
                        $val = $row[$colName];
                        if ($val !== null && $val !== '') {
                            try {
                                $valUxon = UxonObject::fromAnything($val); 
                                $rows[$i][$colName] = $valUxon->toArray();
                            } catch (\Throwable $e) {
                                // Ignore errors
                            }
                        }
                    }
            }
        }
        return $rows;
    }
    
    /**
     * 
     * @param array $rows
     * @param string $filterRowName
     * @param string $filterRowValue
     * @return array
     */
    protected function filterRows(array $rows, string $filterRowName, string $filterRowValue)
    {
        $filter = new RowDataArrayFilter();
        $filter->addAnd($filterRowName, $filterRowValue, EXF_COMPARATOR_EQUALS);
        return $filter->filter($rows);
    }

    /**
     *
     * @param AppSelectorInterface $app_selector            
     * @param string $source_absolute_path            
     * @return string
     */
    protected function installModel(AppSelectorInterface $app_selector, $source_absolute_path) : \Iterator
    {
        $modelChanged = false;
        $indent = $this->getOutputIndentation();
        yield $indent . "Model changes:" . PHP_EOL;
        
        $model_source = $this->getDataFolderPathAbsolute($source_absolute_path);
        
        if (is_dir($model_source)) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            foreach ($this->readModelSheetsFromFolders($model_source) as $data_sheet) {
                try {
                    
                    // Remove columns, that are not attributes. This is important to be able to import changes on the meta model itself.
                    // The trouble is, that after new properties of objects or attributes are added, the export will already contain them
                    // as columns, which would lead to an error because the model entities for these columns are not there yet.
                    foreach ($data_sheet->getColumns() as $column) {
                        if (! $column->isAttribute() || ! $column->getMetaObject()->hasAttribute($column->getAttributeAlias())) {
                            $data_sheet->getColumns()->remove($column);
                        }
                    }
                    
                    if ($data_sheet->getMetaObject()->is('exface.Core.BASE_OBJECT')) {
                        if ($mod_col = $data_sheet->getColumns()->getByExpression('MODIFIED_ON')) {
                            $mod_col->setIgnoreFixedValues(true);
                        }
                        if ($user_col = $data_sheet->getColumns()->getByExpression('MODIFIED_BY_USER')) {
                            $user_col->setIgnoreFixedValues(true);
                        }
                    }
                    
                    // Disable timestamping behavior because it will prevent multiple installations of the same
                    // model since the first install will set the update timestamp to something later than the
                    // timestamp saved in the model files
                    foreach ($data_sheet->getMetaObject()->getBehaviors()->getByPrototypeClass(TimeStampingBehavior::class) as $behavior) {
                        $behavior->disable();
                    }
                    // Disable model validation because it would instantiate all objects when the object sheet is being saved,
                    // which will attempt to load an inconsistent model (e.g. because the attributes were not yet updated
                    // at this point.
                    foreach ($data_sheet->getMetaObject()->getBehaviors()->getByPrototypeClass(ModelValidatingBehavior::class) as $behavior) {
                        $behavior->disable();
                    }
                    
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
                    $ex = new InstallerRuntimeError($this, 'Failed to install ' . $data_sheet->getMetaObject()->getAlias() . '-sheet: ' . $e->getMessage(), null, $e);
                    throw $ex;
                }
            }
            
            if ($modelChanged === false) {
                yield $indent.$indent."No changes found" . PHP_EOL;
            }
            
            // Install pages.
            $pageInstaller = $this->getPageInstaller();
            $pageInstaller->setOutputIndentation($indent);
            $pageInstaller->setTransaction($transaction);
            yield from $pageInstaller->install($source_absolute_path);
            
            // Commit the transaction
            $transaction->commit();
        } else {
            yield $indent . "No model files to install" . PHP_EOL;
        }
    }
    
    protected function getPageInstaller() : PageInstaller
    {
        return new PageInstaller($this->getSelectorInstalling(), self::FOLDER_NAME_MODEL . DIRECTORY_SEPARATOR . self::FOLDER_NAME_PAGES);
    }
    
    /**
     * 
     * @param string $path
     * @param string $contents
     * @return string
     */
    protected function applyCompatibilityFixesToFileContent(string $path, string $contents) : string
    {
        // upgrade to 0.28: Translate old error model to message model
        $filename = mb_strtolower(basename($path));
        if ($filename === '07_error.json') {
            $replaceFrom = [
                'exface.Core.ERROR',
                'ERROR_CODE',
                'ERROR_TEXT'
            ];
            $replaceTo = [
                'exface.Core.MESSAGE',
                'CODE',
                'TITLE'
            ];
            $contents = str_replace($replaceFrom, $replaceTo, $contents);
        }
        
        // upgrade to 0.29: The LABEL attribute of object and attributes was replaced by NAME.
        if ($filename === '02_object.json' || $filename === '04_attribute.json') {
            $objObject = $this->getWorkbench()->model()->getObject('exface.Core.OBJECT');
            
            // Make older model files work with new model (0.29+)
            // If there is no NAME-column, rename the LABEL column to NAME.
            if ($objObject->hasAttribute('NAME') === true && strpos($contents, '{
            "name": "NAME",
            "attribute_alias": "NAME"
        }') === false) {
                
                // Replace the columns entry
                $contents = str_replace('{
            "name": "LABEL",
            "attribute_alias": "LABEL"
        }', '{
            "name": "NAME",
            "attribute_alias": "NAME"
        }', $contents);
                
                // Replace the row data
                $contents = str_replace('"LABEL": "', '"NAME": "', $contents);
                
            }
            // Make older models work with new model files (needed to upagrade to new model)
            // Replace things right the other way around.
            elseif ($objObject->hasAttribute('NAME') === false) {
                // If there is no NAME-column, rename the LABEL column to NAME.
                if (strpos($contents, '{
            "name": "LABEL",
            "attribute_alias": "LABEL"
        }') === false) {
        
                    // Replace the columns entry
                    $contents = str_replace('{
            "name": "NAME",
            "attribute_alias": "NAME"
        }', '{
            "name": "LABEL",
            "attribute_alias": "LABEL"
        }', $contents);
                
                    // Replace the row data
                    $contents = str_replace('"NAME": "', '"LABEL": "', $contents);
                
                }
            }
        }
        
        return $contents;
    }
    
    /**
     * 
     * @param DataSheetInterface $sheet
     * @return DataSheetInterface
     */
    protected function applyCompatibilityFixesToDataSheet(DataSheetInterface $sheet) : DataSheetInterface
    {
        // Upgrade to 1.2: add copyable-attribute to any legacy attribute sheet and make it
        // get the values from the editable-attribute. This ensures backwards compatibility
        // because the CopyData action copied editable attributes before a dedicated copyable
        // flag was introduced.
        if ($sheet->getMetaObject()->isExactly('exface.Core.ATTRIBUTE')) {
            if (! $sheet->getColumns()->getByExpression('COPYABLEFLAG') && $editableCol = $sheet->getColumns()->getByExpression('EDITABLEFLAG')) {
                $sheet->getColumns()->addFromExpression('COPYABLEFLAG')->setValues($editableCol->getValues(false));
            }
        }
        
        // Upgrade to 1.7: move legacy filter_context property of data connections to their config
        if ($sheet->getMetaObject()->isExactly('exface.Core.CONNECTION')) {
            if ($contextCol = $sheet->getColumns()->getByExpression('FILTER_CONTEXT')) {
                $configCol = $sheet->getColumns()->getByExpression('CONFIG');
                foreach ($contextCol->getValues() as $rowIdx => $contextJson) {
                    if ($contextJson === '' || $contextJson === null) {
                        continue;
                    }
                    $contextUxon = UxonObject::fromJson($contextJson);
                    if ($contextUxon->isEmpty()) {
                        continue;
                    }
                    $configJson = $configCol->getValue($rowIdx);
                    $configJson = $configJson === '' || $configJson === null ? '{}' : $configJson;
                    $configUxon = UxonObject::fromJson($configJson);
                    $configUxon->setProperty('filter_context', $contextUxon);
                    $configCol->setValue($rowIdx, $configUxon->toJson());
                }
                $sheet->getColumns()->remove($contextCol);
            }
        }
        
        return $sheet;
    }
    
    /**
     * 
     * @param string $uid
     * @return string
     */
    protected function getObjectSubfolder(string $uid) : string
    {
        if ($this->objectSheet !== null) {
            $row = $this->objectSheet->getRow($this->objectSheet->getUidColumn()->findRowByValue($uid));
            $alias = $this->getApp()->getAliasWithNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $row['ALIAS'];
        }
        
        if (! $alias) {
            $alias = $this->getWorkbench()->model()->getObject($uid)->getAliasWithNamespace();
        }
        
        return trim($alias);
    }
}