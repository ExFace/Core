<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

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
 * - Security/
 *      - PageGroups/
 *          - <name_of_page_group>
 *              - 12_PAGE_GROUP.json
 *              - 13_PAGE_GROUP_PAGES.json
 *      - UserRoles/
 *          - <alias_of_role>
 *              - 14_USER_ROLE.json
 *              - 16_AUTHORIZATION_POLICY.json
 * - 00_APP.json <- entities without an object binding are stored as data sheet UXON
 * - 01_DATATYPE.json
 * - ...
 * - 99_PAGES
 *      - app.alias.page_alias_1.json
 *      - app.alias.page_alias_2.json
 *      - ... 
 * 
 * Object-bound entities are first collected into a single large data sheet to make sure all
 * data is replaced at once.
 * 
 * Pages are installed last by calling the dedicated `PageInstaller`.
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
    
    /**
     * 
     * @param SelectorInterface $selectorToInstall
     */
    public function __construct(SelectorInterface $selectorToInstall)
    {
        parent::__construct($selectorToInstall);
        
        $this->setDataFolderPath(self::FOLDER_NAME_MODEL);
        
        $translitRule = ':: Any-Latin; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC; :: [:Punctuation:] Remove;';
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
        $this->addDataOfObject('exface.Core.PAGE_GROUP', 'CREATED_ON', 'APP', [], 'Security/PageGroups/[#=Transliterate(NAME, "' . $translitRule . '")#]/12_PAGE_GROUP.json');
        $this->addDataOfObject('exface.Core.PAGE_GROUP_PAGES', 'CREATED_ON', 'PAGE__APP', [], 'Security/Page groups/[#=Transliterate(PAGE_GROUP__NAME, "' . $translitRule . '")#]/13_PAGE_GROUP_PAGES.json');
        $this->addDataOfObject('exface.Core.USER_ROLE', 'CREATED_ON', 'APP', [], 'Security/UserRoles/[#ALIAS#]/14_USER_ROLE.json');
        $this->addDataOfObject('exface.Core.AUTHORIZATION_POINT', 'CREATED_ON', 'APP', [
            'DEFAULT_EFFECT',
            'DEFAULT_EFFECT_LOCAL',
            'COMBINING_ALGORITHM',
            'COMBINING_ALGORITHM_LOCAL',
            'DISABLED_FLAG'
        ], 'Security/15_AUTHORIZATION_POINT.json');
        $this->addDataOfObject('exface.Core.AUTHORIZATION_POLICY', 'CREATED_ON', 'APP', [
            'DISABLED_FLAG'
        ], 'Security/UserRoles/[#TARGET_USER_ROLE__ALIAS#]/16_AUTHORIZATION_POLICY.json');
        $this->addDataOfObject('exface.Core.QUEUE', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.SCHEDULER', 'CREATED_ON', 'APP', [
            'LAST_RUN'
        ]);
        $this->addDataOfObject('exface.Core.COMMUNICATION_CHANNEL', 'CREATED_ON', 'APP');
        $this->addDataOfObject('exface.Core.COMMUNICATION_TEMPLATE', 'CREATED_ON', 'APP');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\DataInstaller::getModelFilePath()
     */
    protected function getModelFilePath(MetaObjectInterface $object) : string
    {
        $folder = '';
        switch (true) {
            case $object->isExactly('exface.Core.OBJECT'):
                $folder = '[#ALIAS_WITH_NS#]' . DIRECTORY_SEPARATOR;
                break;
            case $object->isExactly('exface.Core.ATTRIBUTE_COMPOUND'):
                $folder = '[#COMPOUND_ATTRIBUTE__OBJECT__ALIAS_WITH_NS#]' . DIRECTORY_SEPARATOR;
                break;
            default:
                foreach ($object->getAttributes() as $attr) {
                    if ($attr->isRelation() 
                        && $attr->getRelation()->getRightObject()->isExactly('exface.Core.OBJECT') 
                        && $attr->getRelation()->isForwardRelation() 
                        && $attr->isRequired()
                    ) {
                        $folder = "[#{$attr->getAlias()}__ALIAS_WITH_NS#]" . DIRECTORY_SEPARATOR;
                        break;
                    }
                }
        }
        $filename = parent::getModelFilePath($object);
        return $folder . $filename;
    }

    /**
     *
     * @param string $source_absolute_path
     * @return string
     */
    public function install(string $source_absolute_path) : \Iterator 
    {
        $indent = $this->getOutputIndentation();
        $model_source = $this->getDataFolderPathAbsolute($source_absolute_path);
        yield $indent . "Model changes:" . PHP_EOL;
        
        if (is_dir($model_source)) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $generator = $this->installModel($model_source, $transaction, $indent);
            yield from $generator;
            
            $modelChanged = $generator->getReturn();
            if ($modelChanged === true) {
                // Make sure to clear the model cache after changes to ensure all objects loaded before and after this point have
                // consistent models
                $this->getWorkbench()->model()->clearCache();
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\DataInstaller::backup()
     */
    public function backup(string $destinationAbsolutePath) : \Iterator
    {
        $idt = $this->getOutputIndentation();
        $app = $this->getApp();
        
        yield from parent::backup($destinationAbsolutePath);
        
        // Save some information about the package in the extras of composer.json
        $package_props = array(
            'app_uid' => $app->getUid(),
            'app_alias' => $app->getAliasWithNamespace()
        );
        
        $packageManager = $this->getWorkbench()->getApp("axenox.PackageManager");
        $composer_json = $packageManager->getComposerJson($app);
        $composer_json['extra']['app'] = $package_props;
        $packageManager->setComposerJson($app, $composer_json);
        
        yield $idt . 'Updated composer.json for "' . $app->getAliasWithNamespace() . '".' . PHP_EOL;
        
        // Backup pages.
        $pageInstaller = $this->getPageInstaller();
        $pageInstaller->setOutputIndentation($idt);
        yield from $pageInstaller->backup($destinationAbsolutePath);
    }
    
    /**
     * 
     * @return PageInstaller
     */
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
}