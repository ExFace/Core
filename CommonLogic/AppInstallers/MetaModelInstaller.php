<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Behaviors\TimeStampingBehavior;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\QueryBuilder\RowDataArrayFilter;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Behaviors\ModelValidatingBehavior;
use exface\Core\DataTypes\UxonDataType;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\EncryptedDataType;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Exceptions\EncryptionError;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Saves all model entities and eventual custom added data as JSON files in the `Model` subfolder of the app.
 * 
 * ## What is exported
 * 
 * By default, this installer will export the entire model of an app as JSON. You can also
 * add specific external content via `addModelDataSheet`. These data sheets (called
 * `additions`) will be exported into subfolders of the default `Model` folder (see below).
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
class MetaModelInstaller extends AbstractAppInstaller
{
    const FOLDER_NAME_MODEL = 'Model';
    
    const FOLDER_NAME_PAGES = '99_PAGE';
    
    const ENCRYPTION_CONFIG_FILE = 'Encryption.config.json';
    
    private $objectSheet = null;
    
    private $additions = [];
    
    private $salt = null;

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
        
        // Uninstall additions first as the may depend on the apps model
        $additionSheets = $this->getAdditions();
        $additionSheets = array_reverse($additionSheets);
        foreach ($additionSheets as $addition) {
            $sheet = $addition['sheet'];
            
            if ($sheet->isUnfiltered()) {
                yield $idt . $idt . 'Cannot uninstall ' . $sheet->getMetaObject()->__toString() . ': data has no app relation!';
            }
            
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
        
        // Uninstall the main model now
        // Make sure to load all objects before starting to delete, so that their model
        // is available at delete time for cascading deletes, etc. If not done so, relations
        // might get broken because the data sources get removed before the objects and
        // the objects loose their base attributes. We've had broken self-relations because
        // an object inherited the UID from a data source base object, which was not present
        // anymore when the object was loaded.
        $modelSheets = $this->getCoreModelSheets();
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
        $dir = $destinationAbsolutePath . DIRECTORY_SEPARATOR . self::FOLDER_NAME_MODEL;
        
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
        foreach ($this->getCoreModelSheets() as $nr => $ds) {
            $ds->dataRead();
            $this->exportModelFile($dir, $ds, str_pad($nr, 2, '0', STR_PAD_LEFT) . '_', true, $dirOld);
        }
        // Save additions
        $additionCnt = [];
        foreach ($this->getAdditions() as $addition) {
            $ds = $addition['sheet'];
            $subdir = $addition['subfolder'];
            $lastUpdAlias = $addition['lastUpdateAttributeAlias'];
            
            if ($ds->isUnfiltered()) {
                yield $idt . 'Cannot backup ' . $ds->getMetaObject()->__toString() . ': data has no app relation!';
            }
            
            $ds->dataRead();
            $nr = $additionCnt[$subdir] = ($additionCnt[$subdir] ?? 0) + 1;
            $this->exportModelFile($dir . DIRECTORY_SEPARATOR . $subdir, $ds, str_pad($nr, 2, '0', STR_PAD_LEFT) . '_', false, $dirOld);
            if (! $lastUpdAlias && $ds->getMetaObject()->is('exface.Core.BASE_OBJECT')) {
                $lastUpdAlias = 'MODIFIED_ON';
            }
        }
        
        // Save some information about the package in the extras of composer.json
        $package_props = array(
            'app_uid' => $app->getUid(),
            'app_alias' => $app->getAliasWithNamespace(),
            /* TODO remove hash and timestamp completely as they mainly cause merge conflicts
             * and their value is not proportional.
            'model_md5' => md5($model_string),
            'model_timestamp' => $last_modification_time*/
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
                case $dataType instanceof UxonDataType:
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
    
    protected function filterRows(array $rows, string $filterRowName, string $filterRowValue)
    {
        $filter = new RowDataArrayFilter();
        $filter->addAnd($filterRowName, $filterRowValue, EXF_COMPARATOR_EQUALS);
        return $filter->filter($rows);
    }

    /**
     *
     * @param AppInterface $app            
     * @return DataSheetInterface[]
     */
    protected function getCoreModelSheets() : array
    {
        $sheets = array();
        $app = $this->getApp();      
        $model = $this->getWorkbench()->model();
        $sheets = array();
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.APP'), 'UID', ['PUPLISHED']);
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.DATATYPE'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.OBJECT'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.OBJECT_BEHAVIORS'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.ATTRIBUTE'), 'OBJECT__APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.DATASRC'), 'APP', [
            'CONNECTION',
            'CUSTOM_CONNECTION',
            'QUERYBUILDER',
            'CUSTOM_QUERY_BUILDER'
        ]);
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.CONNECTION'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.MESSAGE'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.OBJECT_ACTION'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.UXON_PRESET'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.PAGE_TEMPLATE'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.ATTRIBUTE_COMPOUND'), 'COMPOUND_ATTRIBUTE__OBJECT__APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.PAGE_GROUP'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.PAGE_GROUP_PAGES'), ['PAGE__APP', 'PAGE_GROUP__APP']);
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.USER_ROLE'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.AUTHORIZATION_POINT'), 'APP', [
            'DEFAULT_EFFECT',
            'DEFAULT_EFFECT_LOCAL',
            'COMBINING_ALGORITHM',
            'COMBINING_ALGORITHM_LOCAL',
            'DISABLED_FLAG'
        ]);
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.AUTHORIZATION_POLICY'), 'APP', [
            'DISABLED_FLAG'
        ]);
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.QUEUE'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.SCHEDULER'), 'APP', [
            'LAST_RUN'
        ]);
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.COMMUNICATION_CHANNEL'), 'APP');
        $sheets[] = $this->createCoreModelSheet($app, $model->getObject('exface.Core.COMMUNICATION_TEMPLATE'), 'APP');
        
        return $sheets;
    }
    
    public function addModelDataSheet(string $subfolder, DataSheetInterface $sheetToExport, string $lastUpdateAttributeAlias = null) : MetaModelInstaller
    {
        $this->additions[] = [
            'sheet' => $sheetToExport,
            'subfolder' => $subfolder,
            'lastUpdateAttributeAlias' => $lastUpdateAttributeAlias
        ];
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getAdditions() : array
    {
        return $this->additions;
    }

    /**
     *
     * @param AppInterface $app            
     * @param MetaObjectInterface $object            
     * @param string|string[] $app_filter_attribute_alias   
     * @param array $exclude_attribute_aliases         
     * @return DataSheetInterface
     */
    protected function createCoreModelSheet($app, MetaObjectInterface $object, $app_filter_attribute_alias, array $exclude_attribute_aliases = array()) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObject($object);
        foreach ($object->getAttributeGroup('~WRITABLE')->getAttributes() as $attr) {
            if (in_array($attr->getAlias(), $exclude_attribute_aliases)){
               continue;
            }
            $ds->getColumns()->addFromExpression($attr->getAlias());
        }
        
        $filterAttrs = is_array($app_filter_attribute_alias) ? $app_filter_attribute_alias : [$app_filter_attribute_alias];
        
        foreach ($filterAttrs as $filterAttr) {
            $ds->getFilters()->addConditionFromString($filterAttr, $app->getUid());
        }
        
        $ds->getSorters()->addFromString('CREATED_ON', 'ASC');
        $ds->getSorters()->addFromString($object->getUidAttributeAlias(), 'ASC');
        
        return $ds;
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
        
        $model_source = $source_absolute_path . DIRECTORY_SEPARATOR . self::FOLDER_NAME_MODEL;
        
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
                        $counter = $data_sheet->dataReplaceByFilters($transaction);
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
        $additionUxons = [];
        $folderSheetUxons = $this->readDataSheetUxonsFromFolder($absolutePath);
        
        // Sort by leading numbers in the file names accross all folders
        ksort($folderSheetUxons);
        
        // Organize the UXON objects in an array like [object_alias => [uxon1, uxon2, ...]]
        foreach ($folderSheetUxons as $key => $uxon) {
            $type = StringDataType::substringBefore($key, '@');
            // Additions should be moved to the end of list in case they depend on the core model
            foreach ($this->getAdditions() as $addition) {
                if (StringDataType::endsWith($key, DIRECTORY_SEPARATOR . $addition['subfolder'])) {
                    $additionUxons[$addition['subfolder'].$type][] = $uxon;
                    continue 2;
                }
            }
            $uxons[$type][] = $uxon;
        }
        $uxons = array_merge($uxons, $additionUxons);
        
        // For each object, combine it's UXONs into a single data sheet
        foreach ($uxons as $array) {
            $cnt = count($array);
            // Init the data sheet from the first UXON, but without any rows. We will preprocess
            // the rows later and transform expanded UXON values into strings.
            $baseUxon = $array[0];
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
                switch (true) {                    
                    case $dataType instanceof EncryptedDataType:
                        $colName = $col->getName();
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
                        $colName = $col->getName();
                        foreach ($rows as $i => $row) {
                            $val = $row[$colName];                            
                            if (is_array($val)) {
                                $rows[$i][$colName] = (UxonObject::fromArray($val))->toJson();
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
     * 
     * @param string $absolutePath
     * @return UxonObject[]
     */
    protected function readDataSheetUxonsFromFolder(string $absolutePath) : array
    {
        $folderUxons = [];
        
        if (is_file($absolutePath)) {
            $folderUxons[FilePathDataType::findFileName($absolutePath) . '@' . FilePathDataType::findFolderPath($absolutePath)] = $this->readDataSheetUxonFromFile($absolutePath);
            return $folderUxons;
        }
        
        foreach (scandir($absolutePath) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if ($file === self::FOLDER_NAME_PAGES) {
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