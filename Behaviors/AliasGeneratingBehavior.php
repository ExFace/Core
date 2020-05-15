<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Exceptions\Behaviors\DataSheetCreateDuplicatesForbiddenError;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Factories\AppFactory;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;

/**
 * Behavior to generate a transliterated string out of a string that is given in the data sheet.
 * The attribute alias of the column that contains the source strings to be transliterated is set with the uxon property `source_attribute_alias`.
 * The attribute alias of the column the app uid/alis the namespace should be extracted from that is preceded to the generated string is
 * set with the uxon-property `namespace_attribute_alias`.
 * The attribute alias of the column that the generated string should be saved in is set with the uxon-property `target_attribute_alias`.
 * The string will only be generated if the target cell in the data sheet is empty.
 * 
 * Configuration example:
 * 
 * {
 *  "target_attribute_alias": "ALIAS",
 *  "namespace_attribute_alias": "APP",
 *  "source_attribute_alias": "NAME"
 * }
 * 
 */
class AliasGeneratingBehavior extends AbstractBehavior
{
    
    const CASE_UPPER = 'UPPER';
    
    const CASE_LOWER = 'LOWER';
    
    const TRANSLITERATION_CONFIG = 'Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove;';
    
    const REGEX_DELIMITERS = ['/', '~', '@', ';', '%', '`'];
    
    private $namespaceAttributeAlias = null;
    
    private $sourceAttributeAlias = null;
    
    private $targetAttributeAlias = null;
    
    private $case = null;
    
    private $replaceCharacters = ["Ä"=>"Ae", "Ö"=>"Oe", "Ü"=>"Ue", "ä"=>"ae", "ö"=>"oe", "ü"=>"ue", "ß"=>"ss"];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleOnBeforeCreate']);
        
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleOnBeforeUpdate']);
        
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * 
     * @param OnBeforeCreateDataEvent $event
     * @throws DataSheetCreateDuplicatesForbiddenError
     */
    public function handleOnBeforeCreate(OnBeforeCreateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return ;
        }
        
        $eventSheet = $event->getDataSheet();
        //$eventSheet->getColumns()->set
        $object = $eventSheet->getMetaObject();        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        $eventSheet = $this->generateTransliteratedAliases($eventSheet);
    }
    
    /**
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @throws DataSheetCreateDuplicatesForbiddenError
     */
    public function handleOnBeforeUpdate(OnBeforeUpdateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return ;
        }
        
        $eventSheet = $event->getDataSheet();
        $object = $eventSheet->getMetaObject();
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        $eventSheet = $this->generateTransliteratedAliases($eventSheet);
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return DataSheetInterface
     */
    protected function generateTransliteratedAliases(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $namespace = null;
        $values = $dataSheet->getColumnValues($dataSheet->getColumns()->getByExpression($this->getSourceAttributeAlias())->getName());
        foreach ($values as $idx => $value) {
            if ($value !== null && $value !== '') {               
                if ($this->getNamespaceAttributeAlias() !== null) {
                    $appAliasOrUid = $dataSheet->getCellValue($dataSheet->getColumns()->getByExpression($this->getNamespaceAttributeAlias())->getName(), $idx);
                    if ($appAliasOrUid !== null && $appAliasOrUid !== '') {
                        $selector = SelectorFactory::createAppSelector($this->getWorkbench(), $appAliasOrUid);
                        $app = AppFactory::createFromAnything($selector, $this->getWorkbench());
                        $namespace = $app->getAliasWithNamespace();
                    }
                }
                if ($namespace !== null) {
                    $value = $namespace . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $value;
                }                
                $transliterated = $this->transliterate($value);
                
                $transliterateColumnName = $dataSheet->getColumns()->getByExpression($this->getTargetAttributeAlias())->getName();
                if ($dataSheet->getCellValue($transliterateColumnName, $idx) === null || $dataSheet->getCellValue($transliterateColumnName, $idx) === '') {
                    $dataSheet->setCellValue($transliterateColumnName, $idx, $transliterated);
                }
            }
        }
        
        return $dataSheet;
    }
    
    /**
     * 
     * @param string $string
     * @return string
     */
    protected function transliterate(string $string) : string
    {   
        $string = $this->replaceSpecialCharacters($string);
        $transConfig = self::TRANSLITERATION_CONFIG;
        if ($this->getCase() === self::CASE_LOWER) {
            $transConfig .= ' Lower();';
        }
        if ($this->getCase() === self::CASE_UPPER) {
            $transConfig .= ' Upper();';
        }
        $transliterated = transliterator_transliterate($transConfig, $string);
        return $transliterated;
    }
    
    /**
     * 
     * @param string $string
     * @return string
     */
    protected function replaceSpecialCharacters(string $string) : string
    {
        foreach ($this->getReplaceCharacters() as $exp => $repl) {
            $isRegex = false;
            foreach (self::REGEX_DELIMITERS as $delim) {
                if (StringDataType::startsWith($exp, $delim) === true && StringDataType::endsWith($exp, $delim) === true) {
                    $isRegex = true;
                    break;
                }
            }
            if ($isRegex === true) {
                $string = preg_replace($exp, $repl, $string);
            } else {
                $string = str_replace($exp, $repl, $string);
            }
        }
        return $string;
    }
    
    /**
     * Set the name of the attribute which contains the source value that should be transliterated.
     * 
     * @uxon-property source_attribute_alias
     * @uxon-type string
     * 
     * @param string $alias
     * @return AliasGeneratingBehavior
     */
    public function setSourceAttributeAlias(string $alias) : AliasGeneratingBehavior
    {
        $this->sourceAttributeAlias = $alias;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getSourceAttributeAlias() : string
    {
        return $this->sourceAttributeAlias;
    }
    
    /**
     * Set the app alias which alias including namespace should be added infront of the transliterated name.
     * 
     * @uxon-property namespace_attribute_alias
     * @uxon-type string
     *
     * @param string $alias
     * @return AliasGeneratingBehavior
     */
    public function setNamespaceAttributeAlias(string $alias) : AliasGeneratingBehavior
    {
        $this->namespaceAttributeAlias = $alias;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getNamespaceAttributeAlias() : string
    {
        return $this->namespaceAttributeAlias;
    }
    
    /**
     * Set the attribute alias which should contain the generated value.
     * 
     * @uxon-property target_attribute_alias
     * @uxon-type string
     *
     * @param string $alias
     * @return AliasGeneratingBehavior
     */
    public function setTargetAttributeAlias(string $alias) : AliasGeneratingBehavior
    {
        $this->targetAttributeAlias = $alias;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getTargetAttributeAlias() : string
    {
        return $this->targetAttributeAlias;
    }
    
    /**
     * Set the case the generated string should be. Means all characters in upper or lower case.
     * If this property is not set, character cases will not be changed.
     * Allowed values are `lower` and `upper`.
     * 
     * @uxon-property target_attribute_alias
     * @uxon-type [lower,upper]
     * 
     * @param string $value
     * @throws WidgetPropertyInvalidValueError
     * @return AliasGeneratingBehavior
     */
    public function setCase(string $value) : AliasGeneratingBehavior
    {
        $value = mb_strtoupper($value);
        if (defined(__CLASS__ . '::CASE_' . $value)) {
            $this->case = $value;
        } else {
            throw new WidgetPropertyInvalidValueError('Invalid cases "' . $value . '". Only LOWER and UPPER are allowed!', '6TA2Y6A');
        }
        return $this;        
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getCase() : ?string
    {
        return $this->case;
    }
    
    /**
     * @uxon-property replace_characters
     * @uxon-type UxonObject 
     * @uxon-template {""} 
     * 
     * @param UxonObject $uxonObject
     * @return AliasGeneratingBehavior
     */
    public function setReplaceCharacters (UxonObject $uxonObject) : AliasGeneratingBehavior
    {
        $this->replaceCharacters = array_merge($this->replaceCharacters, $uxonObject->toArray());
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getReplaceCharacters() : array
    {
        return $this->replaceCharacters;
    }
}