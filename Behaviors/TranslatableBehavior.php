<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Model\Behaviors\TranslatableRelation;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Widgets\InputKeysValues;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\UxonDataType;
use exface\Core\Factories\UxonSchemaFactory;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\Translation;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Events\Model\OnMetaObjectActionLoadedEvent;
use exface\Core\Events\Model\OnUiMenuItemLoadedEvent;
use exface\Core\Events\Model\OnBeforeDefaultObjectEditorInitEvent;
use exface\Core\Events\Model\OnBeforeMetaObjectActionLoadedEvent;
use exface\Core\Events\Errors\OnErrorCodeLookupEvent;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\DataTypes\LocaleDataType;

/**
 * Makes the data of certain attributes of the object translatable.
 * 
 * For example, if you have a meta object for, let's say, some sort of categories, you may want
 * to make the name and descripion of each category translatable. This behavior let's you translate
 * them independently from the data source, saving translations in JSON files inside the app folder
 * - see example below.
 * 
 * This behavior is also used in the core to make the model itself translatable (i.e. for showing
 * attributes of actions, pages, users etc. in the correct language) - see detailed description below.
 * 
 * Technically this behavior adds a "translate"-button to the default editor of it's object and
 * makes sure, the translation files are used every time data of the object is loaded.
 * 
 * **IMPORTANT** notes:
 * 
 * - The `translation_filename_attribute_alias` MUST point to an attribute, that uniquely identifies
 * the object! Otherwise the translation will apply to all objects with the same value of that
 * attribute.
 * - Translation files will be saved in the folder `Translations` folder inside the app, that the behavior
 * belongs too unless `translation_app_determined_by_relation` is set.
 * - The `translation_subfolder` (i.e. the path inside the `Translations` folder - like `Messages` in the 
 * built-int behavior for message models) MUST be unique among all meta objects with translatable behaviors!
 * 
 * ## Simple example: translating categories
 * 
 * To make the category-example above work, we would need the following behavior on the hypothetical
 * category object:
 * 
 * ```
 * {
 *  "translation_filename_attribute_alias": "ID",
 *  "translation_subfolder": "Categories",
 *  "translatable_attributes": [
 *      "NAME",
 *      "DESCRIPTION"
 *  ]
 * }
 * 
 * ```
 * 
 * Now the default editor for categories will automatically have a translate-button, which would open
 * a list with all languages of the app, that is the "owner" of the behavior - except the app's default
 * language as it does not need to be translated. Once the translate-button is pressed for a category, 
 * translation files for all languages in the list will be created for this category automatically. 
 * 
 * For example, if we have `de` and `ru` translations for an app with `en` as default language, the
 * above example will produce the following file structure inside the app's folder for a category with
 * ID=33:
 * 
 * ```
 * Translations
 *  Categories
 *      33.de.json
 *      33.ru.json
 * 
 * ```
 * 
 * Each file will contain a JSON with two keys: `NAME` and `DESCRIPTION`. Their values are the translations
 * for the respective attributes of category 33. 
 * 
 * It is important, that the `translation_filename_attribute_alias` points to an attribute with unique
 * values: in our case, it's the id of the category. Of course, if you have any readable unique keys like
 * the aliases in the meta model, it is a good idea to use them instead of technical ids!
 * 
 * ## How does the model-translation in the core work?
 * 
 * This behavior is also used in the core to make the model entities themselves translatable. The behavior
 * is attached to the following meta objects:
 * 
 * - `exface.Core.OBJECT` to translate names and descriptions of meta objects themselves and their attributes
 * - `exface.Core.PAGE` to translate page names, etc. and translatable UXON properties of the widgets
 * - `exface.Core.OBJECT_ACTION` to translate modeled actions
 * - `exface.Core.MESSAGE` to translate message titles, hints, etc.
 * 
 * These behaviors use advanced configuration options. Have a look at them to get an idea, of what
 * the behavior can do beside the things described above.
 * 
 * @author Andrej Kabachnik
 *
 */
class TranslatableBehavior extends AbstractBehavior
{
    private $translate_attributes = [];
    
    private $translatable_relations = [];
    
    private $translatable_uxon_attributes = [];
    
    private $translatable_uxon_prototype_attribute_alias = null;
    
    private $translation_folder = 'Translations';
    
    private $translation_subfolder = null;
    
    private $translation_filename_attribute_alias = null;
    
    private $translation_app_determined_by_relation = null;
    
    private $staticListeners = [
        "exface.Core.Model.OnMetaObjectLoaded" => [
            "\\exface\\Core\\Behaviors\\TranslatableBehavior::onObjectLoadedTranslateModel"
        ],
        "exface.Core.Model.OnBeforeMetaObjectActionLoaded" => [
            "\\exface\\Core\\Behaviors\\TranslatableBehavior::onActionLoadedTranslateModel"
        ],
        "exface.Core.Model.OnUiMenuItemLoaded" => [
            "\\exface\\Core\\Behaviors\\TranslatableBehavior::onUiMenuItemLoadedTranslate"
        ],
        "exface.Core.Model.OnBeforeDefaultObjectEditorInit" => [
            "\\exface\\Core\\Behaviors\\TranslatableBehavior::onObjectEditorInitTranslate"
        ],
        "exface.Core.Errors.OnErrorCodeLookup" => [
            "\\exface\\Core\\Behaviors\\TranslatableBehavior::onErrorTranslateMessage"
        ]
    ];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $obj = $this->getObject();
        
        if ($obj->isExactly('exface.Core.TRANSLATIONS_FOR_DATA')) {
            $this->getWorkbench()->eventManager()->addListener(OnBeforeActionPerformedEvent::getEventName(), [
                $this,
                'onReadForKeyCreateFiles'
            ]);
            
            $this->getWorkbench()->eventManager()->addListener(OnActionPerformedEvent::getEventName(), [
                $this,
                'onEditDictPrefill'
            ]);
        }
        
        if ($this->hasTranslatableAttributes()) {
            $this->getWorkbench()->eventManager()->addListener(OnBeforeDefaultObjectEditorInitEvent::getEventName(), [
                $this,
                'onObjectEditorInitAddTranslateButton'
            ]);
        }
        
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * Adds a translate-button to the default editor of the behavior's object, so that
     * every instance of the object can be translated. 
     * 
     * E.g. the translatable behavior of the core's `MESSAGE` object adds a translate-button
     * to the editor used when the action `exface.Core.ShowObjectEditDialog` is called on
     * a message.
     * 
     * @param OnBeforeDefaultObjectEditorInitEvent $event
     * @throws BehaviorRuntimeError
     * 
     * @return void
     */
    public function onObjectEditorInitAddTranslateButton(OnBeforeDefaultObjectEditorInitEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        if ($event->getObject() !== $this->getObject()) {
            return;
        }
        
        $editorUxon = $event->getDefaultEditorUxon();
        if (strcasecmp($editorUxon->getProperty('widget_type'), 'Dialog') !== 0) {
            throw new BehaviorRuntimeError($this->getObject(), 'Cannot add translation-button to default editor dialog of object "' . $this->getObject()->getAliasWithNamespace() . '": the default editor must be of type "Dialog"!');
        }
        
        if ($this->isTranslationAppDeterminedByRelation()) {
            $appRel = $this->getRelationToTranslationApp();
            if (! ($appRel->getRightObject()->isExactly('exface.Core.APP') && $appRel->isForwardRelation())) {
                throw new BehaviorConfigurationError($this->getObject(), 'Invalid `translation_app_determined_by_relation` specified in translatable dehavior of ' . $this->getObject()->getAliasWithNamespace() . ': the relation MUST point to the exface.Core.APP!');
            }
            $appRelAlias = $appRel->getAlias();
        }
        
        $editorUxon->appendToProperty('buttons', new UxonObject([
            'caption' => "=TRANSLATE('exface.Core', 'BEHAVIOR.TRANSLATABLE.TRANSLATE_BUTTON_CAPTION')",
            "icon" => "language",
            'object_alias' => 'exface.Core.TRANSLATIONS_FOR_DATA',
            'close_dialog' => false,
            'action' => [
                'alias' => 'exface.Core.ShowDialog',
                'widget' => [
                    "widget_type" => "DataTable",
                    "object_alias" => "exface.Core.TRANSLATIONS_FOR_DATA",
                    "empty_text" => "=TRANSLATE('exface.Core', 'BEHAVIOR.TRANSLATABLE.NO_TRANSLATIONS_FOUND')",
                    "filters" => [
                        [
                            "attribute_alias" => "SUBFOLDER",
                            "comparator" => "==",
                            "input_widget" => [
                                "widget_type" => "InputHidden"
                            ]
                        ],
                        [
                            "attribute_alias" => "DATA_KEY",
                            "input_widget" => [
                                "widget_type" => "InputHidden"
                            ]
                        ],
                        [
                            "attribute_alias" => "APP",
                            "input_widget" => [
                                "widget_type" => "InputHidden"
                            ]
                        ]
                    ],
                    "columns" => [
                        [
                            "attribute_alias" => "LOCALE"
                        ],
                        [
                            "attribute_alias" => "PATHNAME_RELATIVE",
                            "caption" => "=TRANSLATE('exface.Core', 'BEHAVIOR.TRANSLATABLE.TRANSLATION_FILE')"
                        ]
                    ],
                    "buttons" => [
                        [
                            "action_alias" => "exface.Core.ShowObjectEditDialog",
                            "bind_to_double_click" => true
                        ]
                    ]
                ],
                'input_mapper' => [
                    'column_to_filter_mappings' => [
                        [
                            'from' => "='{$this->getTranlationSubfolder()}'",
                            'to' => 'SUBFOLDER',
                            'comparator' => ComparatorDataType::EQUALS
                        ],
                        [
                            'from' => $this->getTranslationFilenameAttributeAlias(),
                            'to' => 'DATA_KEY'
                        ],
                        [
                            'from' => $appRelAlias,
                            'to' => 'APP'
                        ]
                    ]
                ]
            ]
        ]));
        
        return;
    }
    
    /**
     * Aliases of attributes to translate
     * 
     * @uxon-property translatable_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param array|UxonObject $uxonOrArray
     * @return TranslatableBehavior
     */
    public function setTranslatableAttributes($uxonOrArray) : TranslatableBehavior
    {
        if ($uxonOrArray instanceof UxonObject) {
            $this->translate_attributes = $uxonOrArray->toArray();
        } else {
            $this->translate_attributes = $uxonOrArray;
        }
        
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasTranslatableAttributes() : bool
    {
        return empty($this->translate_attributes) === false;
    }
    
    /**
     * 
     * @return array
     */
    protected function getTranslatableAttributeAliases() : array
    {
        return $this->translate_attributes;
    }
    
    /**
     * An attribute of the object to use for generating translation file names
     * 
     * @uxon-property translation_filename_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return TranslatableBehavior
     */
    public function setTranslationFilenameAttributeAlias(string $alias) : TranslatableBehavior
    {
        $this->translation_filename_attribute_alias = $alias;
        return $this;
    }
  
    /**
     * 
     * @return string
     */
    protected function getTranslationFilenameAttributeAlias() : string
    {
        return $this->translation_filename_attribute_alias ?? $this->getObject()->getUidAttributeAlias();
    }
    
    /**
     * Path to the translation files relative to the app's main translations folder
     * 
     * @uxon-property translation_subfolder
     * @uxon-type string
     * 
     * @param string $pathRelativeToTranslationsFolder
     * @return TranslatableBehavior
     */
    public function setTranslationSubfolder(string $pathRelativeToTranslationsFolder) : TranslatableBehavior
    {
        $this->translation_subfolder = $pathRelativeToTranslationsFolder;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getTranlationSubfolder() : string
    {
        return $this->translation_subfolder ?? ucfirst(mb_strtolower($this->getObject()->getAlias()));
    }
    
    protected function getTranslationFolder() : string
    {
        return $this->translation_folder;
    }
    
    /**
     * Path to the folder with all translations relative to the app root - `Translations` by default.
     * 
     * No need to change this setting unless your app uses a custom translator!
     * 
     * @uxon-property translation_folder
     * @uxon-type string
     * @uxon-default Translations
     * 
     * @param string $pathRelativeToAppFolder
     * @return TranslatableBehavior
     */
    public function setTranslationFolder(string $pathRelativeToAppFolder) : TranslatableBehavior
    {
        $this->translation_folder = $pathRelativeToAppFolder;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO
        return $uxon;
    }
    
    /**
     * Translates names, descriptions, etc. of UI pages and menu items whenever they are loaded.
     * 
     * @param OnUiMenuItemLoadedEvent $event
     * 
     * @return void
     */
    public static function onUiMenuItemLoadedTranslate(OnUiMenuItemLoadedEvent $event)
    {
        $menuItem = $event->getMenuItem();
        
        if ($menuItem->hasApp() === false) {
            return;
        }
        
        $translator = $menuItem->getApp()->getTranslator();
        $domain = 'Pages/' . $menuItem->getAliasWithNamespace();
        
        if (! $translator->hasTranslationDomain($domain)) {
            return;
        }
        
        $menuItem->setName($translator->translate('NAME', null, null, $domain, $menuItem->getName()));
        $menuItem->setDescription($translator->translate('DESCRIPTION', null, null, $domain, $menuItem->getDescription()));
        $menuItem->setIntro($translator->translate('INTRO', null, null, $domain, $menuItem->getIntro()));

        return;
    }
    
    /**
     * Translates messages in errors
     * 
     * @param OnErrorCodeLookupEvent $event
     */
    public static function onErrorTranslateMessage(OnErrorCodeLookupEvent $event)
    {
        $e = $event->getException();
        $wb = $event->getWorkbench();
        
        if (($appSel = $e->getMessageAppSelector($wb)) === null) {
            return;
        }
        
        try {
            $app = $wb->getApp($appSel);
            $translator = $app->getTranslator();
            $domain = 'Messages/' . $e->getAlias();
            if (! $translator->hasTranslationDomain($domain)) {
                return;
            }
            
            $e->setMessageTitle($translator->translate('TITLE', null, null, $domain, $e->getMessageTitle($wb)));
            $e->setMessageHint($translator->translate('HINT', null, null, $domain, $e->getMessageHint($wb)));
            $e->setMessageDescription($translator->translate('DESCRIPTION', null, null, $domain, $e->getMessageDescription($wb)));
        } catch (\Throwable $e2) {
            $wb->getLogger()->logException($e2);
        }
    }
    
    /**
     * Translates names and descriptions of object actions whenever they are loaded.
     * 
     * @param OnMetaObjectActionLoadedEvent $event
     * 
     * @return void
     */
    public static function onActionLoadedTranslateModel(OnBeforeMetaObjectActionLoadedEvent $event) 
    {
        $app = $event->getApp();
        
        $translator = $app->getTranslator();
        $domain = 'Actions/' . $event->getActionAliasWithNamespace();
        
        if (! $translator->hasTranslationDomain($domain)) {
            return;
        }
        
        $uxon = $event->getUxon();
        $translated = $translator->translateUxonProperties($uxon, $domain, 'CONFIG_UXON');
        foreach ($translated->getPropertiesAll() as $prop => $value) {
            $uxon->setProperty($prop, $value);
        }
        
        $uxon->setProperty('name', $translator->translate('NAME', null, null, $domain, $uxon->getProperty('name')));
        $uxon->setProperty('hint', $translator->translate('SHORT_DESCRIPTION', null, null, $domain, $uxon->getProperty('hint') ?? ''));
        
        return;
    }
    
    /**
     * Translates names and descriptions of an object and it's attributes whenever the object is loaded.
     * 
     * @param OnMetaObjectLoadedEvent $event
     * 
     * @return void
     */
    public static function onObjectLoadedTranslateModel(OnMetaObjectLoadedEvent $event)
    {
        $object = $event->getObject();
        
        $translator = $object->getApp()->getTranslator();
        $domain = 'Objects/' . $object->getAliasWithNamespace();
        
        if (! $translator->hasTranslationDomain($domain)) {
            return;
        }
        
        $object->setName($translator->translate('NAME', null, null, $domain, $object->getName()));
        $object->setShortDescription($translator->translate('NAME', null, null, $domain, $object->getShortDescription()));
        
        foreach ($object->getAttributes() as $attr) {
            if ($attr->isInherited()) {
                continue;
            }
            
            $ns = 'ATTRIBUTE.' . Translation::buildTranslationKey([$attr->getAlias()]) . '.';
            $attr->setName($translator->translate($ns . 'NAME', null, null, $domain, $attr->getName()));
            $attr->setShortDescription($translator->translate($ns . 'SHORT_DESCRIPTION', null, null, $domain, $attr->getShortDescription()));
        }
        
        return;       
    }
    
    /**
     * Translates the default editor UXON of the event's object.
     *  
     * @param OnBeforeDefaultObjectEditorInitEvent $event
     * @return void
     */
    public static function onObjectEditorInitTranslate(OnBeforeDefaultObjectEditorInitEvent $event)
    {
        $object = $event->getObject();
        $uxon = $event->getDefaultEditorUxon();
        $translated = $object->getApp()->getTranslator()->translateUxonProperties($uxon, 'Objects/' . $object->getAlias(), 'DEFAULT_EDITOR_UXON');
        foreach ($translated->getPropertiesAll() as $prop => $value) {
            $uxon->setProperty($prop, $value);
        }
        return;
    }
    
    /**
     * Creates translation files for every language of the app on every read-operation for data translation files.
     * 
     * @param OnActionPerformedEvent $event
     * 
     * @return void
     */
    public function onReadForKeyCreateFiles(OnBeforeActionPerformedEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $action = $event->getAction();
        
        if (! $action->isExactly('exface.Core.ReadData')) {
            return;
        }
        
        /* @var $action \exface\Core\Actions\ShowObjectEditDialog */
        if (! $action->getMetaObject()->is('exface.Core.TRANSLATIONS_FOR_DATA')) {
            return;
        }
        
        /* @var $dataSheet \exface\Core\Interfaces\DataSheets\DataSheetInterface */
        $dataSheet = $event->getTask()->getInputData();
        
        foreach ($dataSheet->getFilters()->getConditions() as $cond) {
            if ($cond->getAttributeAlias() === 'DATA_KEY') {
                $key = $cond->getValue();
            }
            if ($cond->getAttributeAlias() === 'SUBFOLDER') {
                $subfolder = $cond->getValue();
            }
            if ($cond->getAttributeAlias() === 'APP') {
                $appUid = $cond->getValue();
            }
        }
        
        if (! $key) {
            return;
        }
        
        $app = $appUid ? $this->getWorkbench()->getApp($appUid) : $this->getApp();
        $path = $this->getTranslationBasePath($app) . $subfolder . DIRECTORY_SEPARATOR;
        
        if (! file_exists($path)) {
            Filemanager::pathConstruct($path);
        }
        
        foreach ($app->getLanguages(false) as $lang) {
            if ($lang === $app->getLanguageDefault()) {
                continue;
            }
            $filepath = $path . $key . '.' . $lang . '.json';
            if (! file_exists($filepath)) {
                file_put_contents($filepath, '{}');
            }
        }
    }
    
    protected function getTranslationBasePath(AppInterface $app, bool $absolute = true) : string
    {
        return ($absolute ? $app->getDirectoryAbsolutePath() : $app->getDirectory()) . DIRECTORY_SEPARATOR . $this->getTranslationFolder() . DIRECTORY_SEPARATOR;
    }
    
    /**
     * Generates all currently relevant translation keys whenever an editor for `exface.Core.TRANSLATIONS_FOR_DATA` is rendered.
     * 
     * If the editor dialog uses the widget `InputKeysValues` for content, reference
     * translations for the app's default language are shown automatically.
     * 
     * FIXME refactor to use the onPrefill event or similar, so that it also works
     * with facades, that load the data separately from the widgets (views).
     * 
     * @param OnActionPerformedEvent $event
     * @return void
     */
    public function onEditDictPrefill(OnActionPerformedEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $action = $event->getAction();
        
        if (! $action->isExactly('exface.Core.ShowObjectEditDialog')) {
            return;
        }
        
        /* @var $action \exface\Core\Actions\ShowObjectEditDialog */
        if (! $action->getMetaObject()->is('exface.Core.TRANSLATIONS_FOR_DATA')) {
            return;
        }
        
        /* @var $dialogWidget \exface\Core\Widgets\Dialog */
        $dialogWidget = $event->getResult()->getWidget();
        $contentWidget = $dialogWidget->findChildrenRecursive(function(WidgetInterface $child) {
            return $child instanceof iShowSingleAttribute && $child->getAttributeAlias() === 'CONTENTS';
        }, 2)[0];
        
        $value = $contentWidget->getValue();
        if (! $value) {
            return;
        }
        $transJson = json_decode($value, true);
        
        $path = $dialogWidget->getPrefillData()->getCellValue('PATHNAME_RELATIVE', 0);
        $subfolder = StringDataType::substringAfter($path, '/' . $this->getTranslationFolder() . '/', '');
        $subfolder = StringDataType::substringBefore($subfolder, '/', $subfolder, false, true);
        $filename = FilePathDataType::findFileName($path);
        $dataKey = StringDataType::substringBefore($filename, '.', $filename, false, true);
        $lang = StringDataType::substringAfter($filename, '.', $filename, false, true);
        
        if (! $dataKey || ! $subfolder) {
            throw new BehaviorRuntimeError($this->getObject(), 'Invalid translation file name: "' . $path . '"!');
        }
        
        $behavior = $this->findBehavior($subfolder);
        
        if ($behavior->isDisabled()) {
            return;
        }
        
        $defLang = $behavior->getObject()->getApp()->getLanguageDefault();
        $coreTranslator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $keyStatus = $coreTranslator->translate('BEHAVIOR.TRANSLATABLE.KEY_STATUS');
        $keyStatusNew = $coreTranslator->translate('BEHAVIOR.TRANSLATABLE.KEY_STATUS_NEW');
        $keyStatusInherited = $coreTranslator->translate('BEHAVIOR.TRANSLATABLE.KEY_STATUS_INHERITED');
        
        $translatables = $this->findTranslatables($behavior, $dataKey);
        $statuses = [];
        $keysExpected = array_keys($translatables);
        $keysFound = array_keys($transJson);
        foreach ($transJson as $key => $val) {
            if ($val === null) {
                $statuses[$key] = $keyStatusInherited;
            }
        }
        
        $missingKeys = array_diff($keysExpected, $keysFound);
        $obsoleteKeys = array_diff($keysFound, $keysExpected);
        // IDEA mark new keys in nother ref-colum?
        foreach ($missingKeys as $key) {
            $transJson = array_merge([$key => null], $transJson);
            $statuses[$key] = $keyStatusNew;
        }
        // IDEA mark obsolete keys in another ref-column and remove them when saving?
        foreach ($obsoleteKeys as $key) {
            unset($transJson[$key]);
        }
        
        $contentWidget->setValue(JsonDataType::encodeJson($transJson, true));
        
        if ($contentWidget instanceof InputKeysValues) {
            $contentWidget->setReferenceValues([
                $keyStatus => $statuses,
                (LocaleDataType::getLocaleName($defLang, $coreTranslator->getLocale()) . ' - ' . $defLang) => $translatables
            ]);
            $contentWidget->setCaptionForKeys($coreTranslator->translate('BEHAVIOR.TRANSLATABLE.TRANSLATION_KEY'));
            $contentWidget->setCaptionForValues(LocaleDataType::getLocaleName($lang, $coreTranslator->getLocale()) . ' - ' . $lang);
        }
        
        return;
    }
    
    /**
     * 
     * @param TranslatableBehavior $behavior
     * @param string $dataKey
     * @throws BehaviorRuntimeError
     * @return string[]
     */
    protected function findTranslatables(TranslatableBehavior $behavior, string $dataKey) : array
    {
        $keys = [];
        
        $ds = DataSheetFactory::createFromObject($behavior->getObject());
        $ds->getColumns()->addMultiple($behavior->getTranslatableAttributeAliases());
        $ds->getColumns()->addMultiple($behavior->getTranslatableUxonAttributeAliases());
        if ($uxonPrototypeAlias = $behavior->getTranslatableUxonPrototypeAttributeAlias()) {
            $uxonPrototypeCol = $ds->getColumns()->addFromExpression($uxonPrototypeAlias);
        }
        $ds->getFilters()->addConditionFromString($behavior->getTranslationFilenameAttributeAlias(), $dataKey, ComparatorDataType::EQUALS);
        $ds->dataRead();
        
        foreach ($behavior->getTranslatableAttributeAliases() as $attrAlias) {
            $keys[Translation::buildTranslationKey([$attrAlias])] = $ds->getCellValue($attrAlias, 0);
        }
        
        foreach ($behavior->getTranslatableUxonAttributeAliases() as $attrAlias) {
            $attr = $behavior->getObject()->getAttribute($attrAlias);
            $uxon = UxonObject::fromJson($ds->getCellValue($attrAlias, 0));
            $prototype = $uxonPrototypeCol ? $uxonPrototypeCol->getValue(0) : null;
            if ($prototype && ! StringDataType::startsWith($prototype, '\\') && StringDataType::endsWith($prototype, '.php', false)) {
                $prototype = FilePathDataType::normalize($prototype, '\\');
                $prototype = '\\' . substr($prototype, 0, -4);
            }
            $keys = array_merge($keys, $this->findTranslatablesInUxon($attr, $uxon, $prototype));
        }
        
        foreach ($behavior->getTranslatableRelations() as $tRel) {
            $keys = array_merge($keys, $this->findTranslatablesInRelations($tRel, $behavior->getTranslationFilenameAttributeAlias(), $dataKey));
        }
        
        return $keys;
    }
    
    /**
     * 
     * @param MetaAttributeInterface $attribute
     * @param UxonObject $uxon
     * @throws BehaviorRuntimeError
     * @return array
     */
    protected function findTranslatablesInUxon(MetaAttributeInterface $attribute, UxonObject $uxon, string $rootPrototypeClass = null) : array
    {
        $dataType = $attribute->getDataType();
        
        if (! $dataType instanceof UxonDataType) {
            throw new BehaviorRuntimeError($attribute->getObject(), 'Cannot translate UXON properties in attribute "' . $attribute->getAliasWithRelationPath() . '" of object "' . $attribute->getObject()->getAliasWithNamespace() . '": attribute is not a UXON!');
        }
        
        $schemaName = $dataType->getSchema();
        $schema = UxonSchemaFactory::create($this->getWorkbench(), $schemaName);
        
        return $this->findTranslatableUxonProperties($uxon, $schema, Translation::buildTranslationKey([$attribute->getAlias()]), $rootPrototypeClass);
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @param UxonSchemaInterface $schema
     * @param string $keyPrefix
     * @return array
     */
    protected function findTranslatableUxonProperties(UxonObject $uxon, UxonSchemaInterface $schema, string $keyPrefix, string $rootPrototypeClass = null) : array
    {
        $translations = [];
        $prototypeClass = $rootPrototypeClass ?? $schema->getPrototypeClass($uxon, []);
        foreach ($schema->getPropertiesByAnnotation('@uxon-translatable', 'true', $prototypeClass) as $prop) {
            if ($uxon->hasProperty($prop)) {
                $val = $uxon->getProperty($prop);
                if (! Expression::detectFormula($val)) {
                    $key = Translation::buildTranslationKey([$keyPrefix, $prop, $val]);
                    $translations[$key] = $val;
                }
            }
        }
        
        foreach ($uxon->getPropertiesAll() as $prop => $val) {
            if ($val instanceof UxonObject) {
                $prototypeClass = $schema->getPrototypeClass($uxon, [$prop, ' '], $rootPrototypeClass);
                $translations = array_merge($translations, $this->findTranslatableUxonProperties($val, $schema, $keyPrefix, $prototypeClass));
            }
        }
        
        return $translations;
    }

    /**
     * 
     * @param TranslatableRelation $transRel
     * @param string $dataKeyAttributeAlias
     * @param string $dataKey
     * @return string[]
     */
    protected function findTranslatablesInRelations(TranslatableRelation $transRel, string $dataKeyAttributeAlias, string $dataKey) : array
    {
        $keys = [];
        $relPath = $transRel->getRelationPath();
        
        if (! $relPath->getRelationFirst()->isReverseRelation()) {
            throw new BehaviorRuntimeError($this->getObject(), 'Cannot get translation keys for translatable relation "' . $relPath->toString() . '" of object "' . $this->getObject()->getAliasWithNamespace() . '": only reverse relations supported!');
        }
        
        $ds = DataSheetFactory::createFromObject($transRel->getRelationPath()->getEndObject());
        $relKeycol = $ds->getColumns()->addFromExpression($transRel->getRelationKeyAttributeAlias(false));
        $ds->getColumns()->addMultiple($transRel->getTranslatableAttributeAliases(false));
        $filterAttrAlias = RelationPath::relationPathAdd($transRel->getRelationPath()->reverse()->toString(), $dataKeyAttributeAlias);
        $ds->getFilters()->addConditionFromString($filterAttrAlias, $dataKey, ComparatorDataType::EQUALS);
        $ds->dataRead();
        
        $prefix = $relPath->toString();
        $relAttrAliases = $transRel->getTranslatableAttributeAliases(false);
        foreach ($ds->getRows() as $row) {
            foreach ($relAttrAliases as $attrAlias) {
                $key = Translation::buildTranslationKey([$prefix, $row[$relKeycol->getName()], $attrAlias]);
                $keys[$key] = $row[$attrAlias];
            }
        }
        
        return $keys;
    }
    
    /**
     * 
     * @param string $subfolder
     * @throws BehaviorRuntimeError
     * @return TranslatableBehavior
     */
    protected function findBehavior(string $subfolder) : TranslatableBehavior
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.OBJECT_BEHAVIORS');
        $ds->getFilters()->addConditionFromString('CONFIG_UXON', '"translation_subfolder":"' . $subfolder . '"');
        $ds->getColumns()->addMultiple([
            OBJECT__UID
        ]);
        $ds->dataRead();
        
        if ($ds->countRows() === 0) {
            throw new BehaviorRuntimeError($this->getObject(), 'Cannot find translatable behavior for subfolder "' . $subfolder . '"!');
        }
        if ($ds->countRows() > 1) {
            throw new BehaviorRuntimeError($this->getObject(), 'Multiple translatable behaviors found for subfolder "' . $subfolder . '"!');
        }
        
        $obj = $this->getWorkbench()->model()->getObjectById($ds->getCellValue('OBJECT__UID', 0));
        foreach ($obj->getBehaviors() as $behavior) {
            if ($behavior instanceof TranslatableBehavior && $behavior->getTranlationSubfolder() === $subfolder) {
                return $behavior;
            }
        }
        
        throw new BehaviorRuntimeError($this->getObject(), 'Cannot find translatable behavior for subfolder "' . $subfolder . '"!');
    }
    
    /**
     * Include translation keys for related data in this translation.
     * 
     * Each translatable relation defines a set of attributes of a reverse relation, that
     * need to be translated inside the dictionary of the behavior's object. For example,
     * this way the metamodel attribute names and descriptions can be translated inside the 
     * meta object's dictionary.
     * 
     * Note, while all aliases need to be defined with relation paths relative to the behavior's
     * object, they all must belong the same related object. It also must be a reverse relation.
     * 
     * @uxon-property translatable_relations
     * @uxon-type \exface\Core\CommonLogic\Model\Behaviors\TranslatableRelation[]
     * @uxon-template [{"relation_key_attribute_alias":"","translatable_attributes":[""]}]
     * 
     * @param UxonObject $uxonArray
     * @return TranslatableBehavior
     */
    public function setTranslatableRelations(UxonObject $uxonArray) : TranslatableBehavior
    {
        foreach ($uxonArray->getPropertiesAll() as $uxon) {
            $this->translatable_relations[] = new TranslatableRelation($this, $uxon);
        }
        return $this;
    }
    
    /**
     * 
     * @return TranslatableRelation[]
     */
    protected function getTranslatableRelations() : array
    {
        return $this->translatable_relations;
    }
    
    public function getTranslatableUxonAttributeAliases() : array
    {
        return $this->translatable_uxon_attributes;
    }
    /**
     * Aliases of attributes, that contain UXON with translatable properties
     * 
     * @uxon-property translatable_uxon_properties
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param UxonObject|string[] $arrayOrUxon
     * @return TranslatableBehavior
     */
    public function setTranslatableUxonProperties($arrayOrUxon) : TranslatableBehavior
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->translatable_uxon_attributes = $arrayOrUxon->toArray();
        } else {
            $this->translatable_uxon_attributes = $arrayOrUxon;
        }
        
        return $this;
    }
    
    public function getTranslatableUxonPrototypeAttributeAlias() : ?string
    {
        return $this->translatable_uxon_prototype_attribute_alias;
    }
    
    /**
     * Use the value of this attribute to determine the prototype of the UXONs with translatable properties.
     * 
     * @uxon-property translatable_uxon_prototype_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return TranslatableBehavior
     */
    public function setTranslatableUxonPrototypeAttribute(string $alias) : TranslatableBehavior
    {
        $this->translatable_uxon_prototype_attribute_alias = $alias;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isTranslationAppDeterminedByRelation() : bool
    {
        return $this->translation_app_determined_by_relation !== null;
    }
    
    /**
     * 
     * @return MetaRelationInterface|NULL
     */
    protected function getRelationToTranslationApp() : ?MetaRelationInterface
    {
        if ($this->translation_app_determined_by_relation === null) {
            return null;
        }
        return $this->getObject()->getRelation($this->translation_app_determined_by_relation);
    }
    
    /**
     * Use this relation of the behavior's object object to determin in which app to save the translations.
     * 
     * By default translation files are saved in the app that the behavior itself belongs to. However, if
     * instances of the translated object can belong to different apps (= the object has a relation to
     * exface.Core.APP), you may choose to save the translations in these apps instead. 
     * 
     * @uxon-property translation_app_determined_by_relation
     * @uxon-type metamodel:relation
     * 
     * @param string $relationAlias
     * @return TranslatableBehavior
     */
    public function setTranslationAppDeterminedByRelation(string $relationAlias) : TranslatableBehavior
    {
        $this->translation_app_determined_by_relation = $relationAlias;
        return $this;
    }
}