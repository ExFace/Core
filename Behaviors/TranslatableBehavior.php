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

/**
 * Makes the data of certain attributes of the object translatable.
 * 
 * @author Andrej Kabachnik
 *
 */
class TranslatableBehavior extends AbstractBehavior
{
    private $translate_attributes = [];
    
    private $translation_subfolder = null;
    
    private $translation_filename_attribute_alias = null;
    
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
            $obj->setDefaultEditorUxon($this->addTranslateButtonToEditor($obj->getDefaultEditorUxon()));
        }
        
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * 
     * @param UxonObject $editorUxon
     * @throws BehaviorRuntimeError
     * @return UxonObject
     */
    protected function addTranslateButtonToEditor(UxonObject $editorUxon) : UxonObject
    {
        if (strcasecmp($editorUxon->getProperty('widget_type'), 'Dialog') !== 0) {
            throw new BehaviorRuntimeError($this->getObject(), 'Cannot add translation-button to default editor dialog of object "' . $this->getObject()->getAliasWithNamespace() . '": the default editor must be of type "Dialog"!');
        }
        $editorUxon->appendToProperty('buttons', new UxonObject([
            'caption' => 'Translate',
            'object_alias' => 'exface.Core.TRANSLATIONS_FOR_DATA',
            'close_dialog' => false,
            'action' => [
                'alias' => 'exface.Core.ShowDialog',
                'widget' => [
                    "widget_type" => "DataTable",
                    "object_alias" => "exface.Core.TRANSLATIONS_FOR_DATA",
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
                        ]
                    ],
                    "columns" => [
                        [
                            "attribute_alias" => "LOCALE"
                        ],
                        [
                            "attribute_alias" => "PATHNAME_RELATIVE"
                        ]
                    ],
                    "buttons" => [
                        [
                            "action_alias" => "exface.Core.ShowObjectEditDialog"
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
                            ]
                            ]
                            ]
                            ]
                            ]));
        
        return $editorUxon;
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
    
    protected function hasTranslatableAttributes() : bool
    {
        return empty($this->translate_attributes) === false;
    }
    
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
     * 
     * @param OnActionPerformedEvent $event
     * @return void
     */
    public function onReadForKeyCreateFiles(OnBeforeActionPerformedEvent $event)
    {
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
        }
        
        if (! $key) {
            return;
        }
        
        $app = $this->getObject()->getApp();
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
    
    protected function getTranslationBasePath(AppInterface $app) : string
    {
        return $app->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'Translations' . DIRECTORY_SEPARATOR;
    }
    
    /**
     *
     * @param OnActionPerformedEvent $event
     * @return void
     */
    public function onEditDictPrefill(OnActionPerformedEvent $event)
    {
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
        $json = json_decode($value, true);
        
        $path = $dialogWidget->getPrefillData()->getCellValue('PATHNAME_RELATIVE', 0);
        $subfolder = StringDataType::substringAfter($path, 'Translations/', '');
        $subfolder = StringDataType::substringBefore($subfolder, '/', $subfolder, false, true);
        $behavior = $this->findBehavior($subfolder);
        
        $keysExpected = $behavior->getTranslatableAttributeAliases();
        $keysFound = array_keys($json);
        
        foreach (array_diff($keysExpected, $keysFound) as $missingKey) {
            $json[$missingKey] = '';
        }
        
        $contentWidget->setValue(JsonDataType::encodeJson($json, true));
        
        return;
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
}