<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\DataTypes\RegularExpressionDataType;

/**
 * Generates a the value for an alias-type attribute from another attribute (typically a name).
 * 
 * Affects update/create operations on data having the target (alias) column. This means, if
 * alias is emptied intentionally, it will be regenerated. This also means, that the behavior
 * will not have effect on operations, that do not inlcude the target column: e.g. partial
 * updates.
 * 
 * Generation is done as follows:
 * 
 * 1. Check if the input data has columns for `source_attribute_alias` and 
 * `target_attribute_alias` (error if not)
 * 2. Prepend a namespace if a `namespace_attribute_alias` is defined
 * 3. Replace characters accoring to `replace_characters` configuration
 * 4. Transliterate the result using PHP's `transliterator_transliterate()`
 * 5. Transform the result's case if required by the `case` property
 * 
 * **NOTE**: Generation is only done if the target cell in the data sheet is empty!
 * 
 * If you need to customize the transliteration, use `replace_characters` to define custom
 * rules. 
 * 
 * ## Examples
 * 
 * This simple example will just "sluggify" an attribute aliased `NAME` and save the
 * result in `ALIAS`.
 * 
 * ```
 * {
 *  "target_attribute_alias": "ALIAS",
 *  "source_attribute_alias": "NAME"
 * }
 * 
 * ```
 * 
 * Here is how alias generation works for the object `exface.Core.PAGES`. In addition
 * to the simplest case, a namespace will be appended, the result will be lowercased
 * and whitespaces will be replaced by `-` instead of the default `_`. Any characters
 * accept for lating letters, `_` and `-` will be removed by the last entry in
 * `replace_characters`.
 * 
 * ```
 * {
 *  "target_attribute_alias": "ALIAS",
 *  "namespace_attribute_alias": "APP__ALIAS",
 *  "source_attribute_alias": "NAME",
 *  "case": "lower",
 *  "replace_characters": {
 *      " ": "-",
 *      "/[^a-zA-Z0-9_-]/": ""
 *  }
 * }
 * 
 * ```
 * 
 */
class AliasGeneratingBehavior extends AbstractBehavior
{
    const CASE_UPPER = 'UPPER';
    
    const CASE_LOWER = 'LOWER';
    
    const TRANSLITERATION_CONFIG = 'Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove;';
    
    private $namespaceAttributeAlias = null;
    
    private $namespaceSeparator = null;
    
    private $sourceAttributeAlias = null;
    
    private $targetAttributeAlias = null;
    
    private $case = null;
    
    private $replaceCharacters = [
        "Ä"=>"Ae", 
        "Ö"=>"Oe", 
        "Ü"=>"Ue", 
        "ä"=>"ae", 
        "ö"=>"oe", 
        "ü"=>"ue", 
        "ß"=>"ss", 
        " "=>"_",
        "&" => "_and_",
        "(" => "",
        ")" => "",
        "[" => "",
        "]" => "",
        "{" => "",
        "}" => "",
        "~" => ""
    ];
    
    private $namespaceCache = [];
    
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
     * @return void
     */
    public function handleOnBeforeCreate(OnBeforeCreateDataEvent $event)
    {
        $this->handleEvent($event);
    }
    
    /**
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @return void
     */
    public function handleOnBeforeUpdate(OnBeforeUpdateDataEvent $event)
    {
        $this->handleEvent($event);
    }
    
    /**
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    protected function handleEvent(DataSheetEventInterface $event)
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
        
        if ($eventSheet->hasAggregations() || $eventSheet->hasAggregateAll()) {
            return;
        }
        
        // If the target column exists and already has all values, don't do anything!
        if ($targetCol = $eventSheet->getColumns()->getByAttribute($this->getTargetAttribute())) {
            if ($targetCol->hasEmptyValues() === false) {
                return;
            }
        } else {
            return;
        }
        
        $this->generateTransliteratedAliases($eventSheet, $targetCol);
        return;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param DataColumnInterface $targetCol
     * 
     * @throws BehaviorRuntimeError
     * 
     * @return DataSheetInterface
     */
    protected function generateTransliteratedAliases(DataSheetInterface $dataSheet, DataColumnInterface $targetCol) : DataSheetInterface
    {
        if ($srcCol = $dataSheet->getColumns()->getByAttribute($this->getSourceAttribute())) {
            $srcValues = $srcCol->getValues();
        } else {
            throw new BehaviorRuntimeError($this->getObject(), $this->getErrorText() . ' from source attribute "' . $this->getSourceAttribute()->getAliasWithRelationPath() . '": no input data found for source attribute found!');
        }
        
        if ($this->hasNamespace()) {
            $nsAttr = $this->getNamespaceAttribute();
            if ($nsCol = $dataSheet->getColumns()->getByAttribute($nsAttr)) {
                if ($nsCol->hasEmptyValues()) {
                    throw new BehaviorRuntimeError($this->getObject(), $this->getErrorText() . ': missing values in input data for namespace column "' . $this->getNamespaceAttribute()->getAliasWithRelationPath() . '"!');
                }
                $nsValues = $nsCol->getValues(false);
            } else {
                if ($nsAttr->isRelated() === false) {
                    throw new BehaviorRuntimeError($this->getObject(), $this->getErrorText() . ': missing values in input data for namespace column "' . $this->getNamespaceAttribute()->getAliasWithRelationPath() . '"!');
                }
                $nsValues = null;
            }
        }
        
        $namespace = null;
        foreach ($srcValues as $rowNo => $srcVal) {
            $targetVal = $targetCol->getCellValue($rowNo);
            if ($targetVal !== null && $targetVal !== '') {
                continue;
            }
            
            if ($srcVal === null || $srcVal === '') {    
                throw new BehaviorRuntimeError($this->getObject(), $this->getErrorText() . ' from source attribute "' . $this->getSourceAttribute()->getAliasWithRelationPath() . '": no input data found for source attribute found!');
            }
            
            if ($this->hasNamespace()) {
                if ($nsValues !== null) {
                    $namespace = $nsValues[$rowNo];
                } else {
                    $namespace = $this->getNamespaceFromRelation($dataSheet, $rowNo);
                }
                
                if ($namespace !== null && $namespace !== '') {
                    // Make sure the alias itself does not have any namespace separators!
                    $srcVal = str_replace($this->getNamespaceSeparator(), '', trim($srcVal));
                    $srcVal = $namespace . $this->getNamespaceSeparator() . $srcVal;
                }
            }
            
            $transliterated = $this->transliterate($srcVal);
            $targetCol->setValue($rowNo, $transliterated);
        }
        
        return $dataSheet;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param int $rowNo
     * 
     * @throws BehaviorRuntimeError
     * 
     * @return string
     */
    protected function getNamespaceFromRelation(DataSheetInterface $dataSheet, int $rowNo) : string
    {
        $nsAttr = $this->getNamespaceAttribute();
        $nsRelPath = $nsAttr->getRelationPath();
        $nsRelLeftKeyAttr = $nsRelPath->getRelationFirst()->getLeftKeyAttribute();
        $nsRelLeftCol = $dataSheet->getColumns()->getByAttribute($nsRelLeftKeyAttr);
        if (! $nsRelLeftCol) {
            throw new BehaviorRuntimeError($this->getObject(), $this->getErrorText() . ': missing values in input data for namespace key column "' . $nsRelLeftKeyAttr->getAliasWithRelationPath() . '"!');
        }
        
        $nsRelLeftKeyVal = $nsRelLeftCol->getCellValue($rowNo);
        if ($nsRelLeftKeyVal === null || $nsRelLeftKeyVal === '') {
            return '';
        }
        if (($ns = $this->namespaceCache[$nsRelLeftKeyVal]) !== null) {
            return $ns;
        }
        
        $revRelPath = $nsRelPath->reverse()->getSubpath(0, -1);
        $revRelAlias = RelationPath::relationPathAdd($revRelPath->toString(), $nsRelPath->getRelationFirst()->getRightKeyAttribute()->getAlias());
        
        $nsSheet = DataSheetFactory::createFromObject($nsRelPath->getEndObject());
        $nsSheet->getFilters()->addConditionFromString($revRelAlias, $nsRelLeftKeyVal, ComparatorDataType::EQUALS);
        $nsCol = $nsSheet->getColumns()->addFromExpression($nsAttr->getAlias());
        $nsSheet->dataRead();
        $ns = $nsCol->getCellValue(0) ?? '';
        $this->namespaceCache[$nsRelLeftKeyVal] = $ns;
        return $ns;
    }
    
    /**
     * 
     * @return string
     */
    protected function getErrorText() : string
    {
        return 'Cannot generate values for attribute "' . $this->getTargetAttribute()->getName() . '" (alias ' . $this->getTargetAttribute()->getAliasWithRelationPath() . '") of object "' . $this->getObject()->getName() . '" (' . $this->getObject()->getAliasWithNamespace() . ')';
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
            if (RegularExpressionDataType::isRegex($exp)) {
                $string = preg_replace($exp, $repl, $string);
            } else {
                $string = str_replace($exp, $repl, $string);
            }
        }
        return $string;
    }
    
    /**
     * The attribute which contains the source value that should be transliterated.
     * 
     * **NOTE**: If a data sheet has a column for `target_attribute_alias` with empty values,
     * it MUST also have this column - otherwise the behavior will throw an error!
     * 
     * @uxon-property source_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $aliasWithRelationPath
     * @return AliasGeneratingBehavior
     */
    public function setSourceAttributeAlias(string $aliasWithRelationPath) : AliasGeneratingBehavior
    {
        $this->sourceAttributeAlias = $aliasWithRelationPath;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    protected function getSourceAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->getSourceAttributeAlias());
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
     * Use this attribute as namespace an prepend it to the generated alias.
     * 
     * The namespace will be separated from the alias by the `namespace_separator`.
     * 
     * You can use related attributes here.
     * 
     * @uxon-property namespace_attribute_alias
     * @uxon-type metamodel:attribute
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
     * @return string|NULL
     */
    protected function getNamespaceAttributeAlias() : ?string
    {
        return $this->namespaceAttributeAlias;
    }
    
    /**
     * A character or string to separate the namespace from the alias.
     * 
     * @uxon-property namespace_delimiter
     * @uxon-type string
     * @uxon-default .
     * 
     * @param string $string
     * @return AliasGeneratingBehavior
     */
    public function setNamespaceSeparator(string $string) : AliasGeneratingBehavior
    {
        $this->namespaceSeparator = $string;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getNamespaceSeparator() : string
    {
        return $this->namespaceSeparator ?? AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;
    }
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    protected function getNamespaceAttribute() : ?MetaAttributeInterface
    {
        if ($this->hasNamespace()) {
            return $this->getObject()->getAttribute($this->getNamespaceAttributeAlias());
        } else {
            return null;
        }
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasNamespace() : bool
    {
        return $this->namespaceAttributeAlias !== null;
    }
    
    /**
     * The attribute where to put the generated value.
     * 
     * **NOTE**: if the input data sheet will not have this column, the behavior
     * will not have any effect!
     * 
     * @uxon-property target_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
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
     * 
     * @return MetaAttributeInterface
     */
    protected function getTargetAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->getTargetAttributeAlias());
    }
    
    /**
     * Set the case the generated string should be. Means all characters in upper or lower case.
     * If this property is not set, character cases will not be changed.
     * Allowed values are `lower` and `upper`.
     * 
     * @uxon-property case
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
     * Custom replacement characters to change transliteration logic.
     * 
     * Put the character, string or regular expression to search for on the left side and
     * the replacement character or string on the right side.
     * 
     * For example, here is how to replace whitespaces by hypens and remove tabs. Note,
     * that the left expression for tabs is a regular expression. Regular expressions
     * MUST start and end with a delimiter. The following delimiters are supported: `/`, 
     * `~`, `@`, `;`, `%`, ```.
     * 
     * ```
     * {
     *  "replace_characters": {
     *      " ": "-",
     *      "/\\t/": ""
     *  }
     * }
     * 
     * ```
     * 
     * Another example: remove all characters except for latin letters and underscores via
     * regular expression:
     * 
     * ```
     * {
     *  "replace_characters": {
     *      "/[^a-zA-Z0-9_]/": ""
     *  }
     * }
     * 
     * ```
     * 
     * @uxon-property replace_characters
     * @uxon-type UxonObject 
     * @uxon-template {"":""} 
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
     * @return string[]
     */
    protected function getReplaceCharacters() : array
    {
        return $this->replaceCharacters;
    }
}