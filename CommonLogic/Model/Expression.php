<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\FormulaFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Exceptions\Model\ExpressionRebaseImpossibleError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Formulas\FormulaInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Exceptions\MetaRelationResolverExceptionInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Factories\ExpressionFactory;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class Expression implements ExpressionInterface
{

    // Expression types
    const TYPE_FORMULA = 'formula';
    const TYPE_ATTRIBUTE = 'attribute_alias';
    const TYPE_STRING = 'string';
    const TYPE_NUMBER = 'number';
    const TYPE_REFERENCE = 'reference';
    const TYPE_UNKNOWN = 'unknown';

    private $formula = null;

    private $widgetLinkString = null;
    
    private $widgetLinks = null;

    private $attribute_alias = null;

    private $value = null;

    private $type = null;

    private $relation_path = null;

    private $originalString = '';

    private $data_type = null;

    private $exface;

    private $meta_object = null;

    /**
     * @deprecated use ExpressionFactory instead!
     * 
     * @param \exface\Core\CommonLogic\Workbench $exface
     * @param string $string
     * @param MetaObjectInterface $meta_object
     * @param bool $parseString
     * @param bool $treatUnknownAsString
     */
    public function __construct(WorkbenchInterface $exface, string $string = null, MetaObjectInterface $meta_object = null, bool $parseString = true, bool $treatUnknownAsString = false)
    {
        $this->exface = $exface;
        $this->meta_object = $meta_object;
        $this->originalString = $string;
        if ($parseString === true) {
            $this->parse($string, $treatUnknownAsString);
        } else {
            $this->parseScalar($string);
        }
    }
    
    /**
     * Parses a string as a scalar (number or string) without attempting to parse formulas, etc.
     * 
     * @param string $string
     * @return void
     */
    protected function parseScalar(string $string = null)
    {
        if (self::detectNumber($string)) {
            $this->type = self::TYPE_NUMBER;
        } else {
            $this->type = self::TYPE_STRING;
        }
        $this->value = $string;
        return;
    }

    /**
     * Parses an ExFace expression
     *
     * @param string|NULL $expression
     * @return void
     */
    protected function parse(string $expression = null, $treatUnknownAsString = false)
    {
        $expression = trim($expression);
        // see, what type of expression it is. Depending on the type, the evaluate() method will give different results.
        $str = $this->parseQuotedString($expression);
        if ($expression === '' || $expression === null || $str !== false) {
            // Check, if it's a string
            // Empty expressions are treated as strings in any case!
            $this->type = self::TYPE_STRING;
            $this->value = $str;
        } elseif ($this::detectNumber($expression)) {
            // Check, if it's a number
            $this->type = self::TYPE_NUMBER;
            $this->value = $expression;
        } elseif (substr($expression, 0, 1) === '=') {
            // If it starts with "=", it can still be anything, so check all possibilities agian 
            if (strpos($expression, '(') !== false && strpos($expression, ')') !== false) {
                // If opening and closing parenthes are present, it's a formula
                $this->type = self::TYPE_FORMULA;
            } else {
                // Otherwise do checks with whatever follows the "="
                $str = substr($expression, 1);
                if ($this::detectQuotedString($str)) {
                    $this->type = self::TYPE_STRING;
                    $this->value = $this->parseQuotedString($str);
                } elseif ($this::detectNumber($str)) {
                    $this->type = self::TYPE_NUMBER;
                    $this->value = $str;
                } else {
                    // If it's neither a quoted string nor a number, it must be a widget link
                    $this->type = self::TYPE_REFERENCE;
                    $this->widgetLinkString = substr($expression, 1);
                    $this->widgetLinks = null;
                }
            }
        } else {
            // Finally, if it's neither a quoted string, nor a number nor does it start with "=", it must be an attribute alias.
            try {
                if (! $this->getMetaObject() || ($this->getMetaObject() && $this->getMetaObject()->hasAttribute($expression))) {
                    $isAttributeAlias = true;
                } else {
                    $isAttributeAlias = false;
                }
            } catch (MetaRelationResolverExceptionInterface $ea) {
                $isAttributeAlias = false;
            }
            if ($isAttributeAlias) {
                $this->attribute_alias = $expression;
                $this->type = self::TYPE_ATTRIBUTE;
            } else {
                // If the expression is neither a formular nor a valid attribute, still treat it as string (this happens mostly
                // when setting widget values (just about in every prefill).
                // FIXME If the prefill value happens to be the same as an attribute or relation path, this is going to produce 
                // strange behavior!
                // FIXME #expression-syntax depending on where the expression comes from, an unquoted string, that is not
                // a valid attribute alias may represent different things. If this expression comes from deserializing a data
                // sheet, it might just be some column id used to add a faked column. If it comes from a widget's setValue()
                // it is surely a string value - see AbstractWidget::setValue(). This is really a mess, that should be cleaned
                // up! There is a lot of testing neede, though.
                if ($treatUnknownAsString === true) {
                    $this->type = self::TYPE_STRING;
                    $this->value = $expression;
                } else {
                    $this->type = self::TYPE_UNKNOWN;
                    $this->value = $str === false ? '' : $str;
                }
            }
        }
        
        return;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isMetaAttribute()
     */
    public function isMetaAttribute() : bool
    {
        return $this->type === self::TYPE_ATTRIBUTE;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isFormula()
     */
    public function isFormula() : bool
    {
        if ($this->type === SELF::TYPE_FORMULA)
            return true;
        else
            return false;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isConstant()
     */
    public function isConstant() : bool
    {
        return ($this->type === self::TYPE_STRING || $this->type === self::TYPE_NUMBER);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isString()
     */
    public function isString() : bool
    {
        return $this->type === self::TYPE_STRING;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isNumber()
     */
    public function isNumber() : bool
    {
        return $this->type === self::TYPE_NUMBER;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isEmpty()
     */
    public function isEmpty() : bool
    {
        return $this->__toString() === null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isLogicalNull()
     */
    public function isLogicalNull() : bool
    {
        return $this->__toString() === EXF_LOGICAL_NULL;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isReference()
     */
    public function isReference() : bool
    {
        return $this->type === SELF::TYPE_REFERENCE;
    }

    /**
     * 
     * @param string|NULL $expression
     * @return string|bool
     */
    protected function parseQuotedString($expression)
    {
        if ($this::detectQuotedString($expression)) {
            return self::unquote($expression);
        } else {
            return false;
        }
    }

    /**
     * Parses the given $expression into a formula instance
     *
     * @param string $expression     
     * @param string $relationPath  
     *      
     * @return FormulaInterface
     */
    protected function parseFormula(string $expression, string $relationPath = null) : FormulaInterface
    {
        if (substr($expression, 0, 1) === '=') {
            $expression = substr($expression, 1); // remove the '='
        }
        $formula = FormulaFactory::createFromString($this->exface, $expression);
        if ($relationPath !== null && $relationPath !== '') {
            $formula = $formula->withRelationPath($relationPath);
        }
        return $formula;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::evaluate()
     */
    public function evaluate(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet = null, $row_number = null)
    {
        // Static valuse (no depending on data)
        if ($this->isStatic() === true) {
            if ($this->isFormula() === true) {
                return $this->getFormula()->evaluate();
            } else {
                return $this->value;
            }
            
        } 
        // Data-driven values
        else {
            if ($data_sheet === null) {
                throw new InvalidArgumentException('In a non-static expression $data_sheet and $column_name are mandatory arguments.');
            }
            
            // If not evaluated for a single row, evaluate() recursively for each row
            if ($row_number === null) {
                $result = array();
                $rows_and_totals_count = $data_sheet->countRows() + count($data_sheet->getTotalsRows());
                for ($r = 0; $r < $rows_and_totals_count; $r ++) {
                    $result[] = $this->evaluate($data_sheet, $r);
                }
                return $result;
            }
            // If in single row context, do the actual evaluation
            switch ($this->type) {
                case self::TYPE_ATTRIBUTE:
                    if ($this->relation_path !== null) {
                        $attrAlias = RelationPath::relationPathAdd($this->relation_path, $this->attribute_alias);
                    } else {
                        $attrAlias = $this->attribute_alias;
                    }
                    $col = $data_sheet->getColumns()->getByExpression($attrAlias);
                    if (! $col) {
                        throw new DataSheetColumnNotFoundError($data_sheet, 'Expression "' . $this->toString() . '" does not match any column in provided data sheet!');
                    }
                    return $col->getCellValue($row_number);
                case self::TYPE_FORMULA:
                    return $this->getFormula()->evaluate($data_sheet, $row_number);
                default:
                    return $this->value;
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::getRequiredAttributes()
     */
    public function getRequiredAttributes() : array
    {
        switch ($this->getType()) {
                case self::TYPE_ATTRIBUTE:
                    return [($this->relation_path ? RelationPath::relationPathAdd($this->relation_path, $this->attribute_alias) : $this->attribute_alias)];
                case self::TYPE_FORMULA:
                    return $this->getFormula()->getRequiredAttributes();           
        }
        return [];
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::getType()
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::getRelationPath()
     */
    public function getRelationPath()
    {
        return $this->relation_path;
    }
    
    /**
     * 
     * @param string $pathString
     * @return ExpressionInterface
     */
    private function setRelationPath(string $pathString) : ExpressionInterface
    {
        $this->relation_path = $pathString;
        // Unset cached formula to force its reinitialization when getFormula() is called.
        $this->formula = null;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::withRelationPath()
     */
    public function withRelationPath(MetaRelationPathInterface $path) : ExpressionInterface
    {
        return $this->copy()->setMetaObject($path->getStartObject())->setRelationPath($path->toString());
    }

    /**
     * @deprecated use __toString() instead!
     */
    public function toString()
    {
        return $this->__toString();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::__toString()
     */
    public function __toString() : string
    {
        switch ($this->getType()) {
            case self::TYPE_ATTRIBUTE:
                // IDEA perhaps it would be better not to prepend the relation path here as it originates
                // from withRelationPath() which should not modify the expression. On the other hand, the
                // premise that chaining withRelationPath() will replace the previous path is fulfilled here.
                // There are also many places in the code, that assume that toString() of an attribute
                // expression will yield the alias including the relation path...
                return ($this->relation_path ? RelationPath::relationPathAdd($this->relation_path, $this->attribute_alias) : $this->attribute_alias);
            default:
                return $this->originalString ?? '';
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::getDataType()
     */
    public function getDataType() : DataTypeInterface
    {
        if (is_null($this->data_type)) {
            switch ($this->type) {
                case self::TYPE_STRING:
                    $this->data_type = DataTypeFactory::createFromString($this->exface, StringDataType::class);
                    break;
                case self::TYPE_NUMBER:
                    $this->data_type = DataTypeFactory::createFromString($this->exface, NumberDataType::class);
                    break;
                case self::TYPE_FORMULA:
                    $this->data_type = $this->getFormula()->getDataType();
                    break;
                case self::TYPE_ATTRIBUTE:
                    if (! is_null($this->getMetaObject())) {
                        $attribute_type = $this->getAttribute()->getDataType();
                        if ($aggr = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $this->toString())) {
                            $this->data_type = $aggr->getResultDataType($attribute_type);
                        } else {
                            $this->data_type = $attribute_type->copy();
                        }
                        break;
                    }                 
                default:
                    $this->data_type = DataTypeFactory::createBaseDataType($this->exface);
            }
        }
        return $this->data_type;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::setDataType()
     */
    public function setDataType($value)
    {
        $this->data_type = $value;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::getMetaObject()
     */
    public function getMetaObject()
    {
        return $this->meta_object;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::setMetaObject()
     */
    public function setMetaObject(MetaObjectInterface $object) : ExpressionInterface
    {
        $this->meta_object = $object;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::rebase()
     */
    public function rebase($relation_path_to_new_base_object)
    {
        if ($this->isFormula()) {
            // TODO Implement rebasing formulas. It should be possible via recursion.
            $this->getWorkbench()->getLogger()->error('Cannot rebase formula "' . $this->toString() . '" - leaving the formula as-is (see Expression::rebase())');
            return $this->copy();
        } elseif ($this->isMetaAttribute()) {
            try {
                $rel = $this->getMetaObject()->getRelation($relation_path_to_new_base_object);
            } catch (MetaRelationNotFoundError $e) {
                throw new ExpressionRebaseImpossibleError('Cannot rebase expression "' . $this->toString() . '" relative to "' . $relation_path_to_new_base_object . '" - invalid relation path given!', '6TBX1V2');
            }
            $thisStr = $this->toString();
            switch (true) {
                // If the realtion path to the new object is just the beginning of the expression, cut it off, returning whatever is left
                // $new_expression_string = RelationPath::relaton_path_cut($thisStr, $relation_path_to_new_base_object);
                case stripos($thisStr, $relation_path_to_new_base_object . RelationPath::getRelationSeparator()) === 0:
                    $new_expression_string = ltrim(substr($thisStr, strlen($relation_path_to_new_base_object)), "_");
                    // Double-check if the remaining string starts with a relation alias modifier. If so, check
                    // if that modifier matches the modifier of the $rel.
                    // This can happen for relations with optional modifiers, when the rebase-path has the short
                    // notation (without modifier) and the expression uses the long notation.
                    if (substr($new_expression_string, 0, 1) === '[') {
                        $relModifier = StringDataType::substringBefore($new_expression_string, ']', '', false, false);
                        $relModifier = trim($relModifier, "[]");
                        if ($relModifier === $rel->getAliasModifier()) {
                            $new_expression_string = StringDataType::substringAfter($new_expression_string, ']');
                            $new_expression_string = ltrim($new_expression_string, "_");
                            break;
                        }
                        // Continue with the next case because this is not our relation!
                    } else {
                        break;
                    }
                // If the expression is the beginning of the relation path, do it the other way around
                // $new_expression_string = RelationPath::relaton_path_cut($relation_path_to_new_base_object, $thisStr);
                case stripos($relation_path_to_new_base_object, $thisStr . RelationPath::getRelationSeparator()) === 0: 
                    $new_expression_string = ltrim(substr($relation_path_to_new_base_object, strlen($thisStr)), "_");
                    break;
                // Otherwise append the expression to the relation path (typically the expression is a direct attribute here an would need
                // a relation path, if referenced from another object).
                default:
                    $new_expression_string = RelationPath::relationPathReverse($relation_path_to_new_base_object, $this->getMetaObject());
                    // Pay attention to reverse relations though: if the expression is the key of the main_object_key of the relation,
                    // we don't need to append it. The related_object_key (foreign key) will suffice. That is, if we need to rebase the reverse
                    // relation POSITION of the the object ORDER relative to that object, we will get ORDER (because POSITION->ORDER ist the
                    // opposite of ORDER<-POSITION). Rebasing POSITION->ORDER->UID from ORDER to POSITION will yield ORDER->UID though because
                    // the UID attribute is explicitly referenced here.
                    // IDEA A bit awqard is rebasing "POSITION->ORDER" from ORDER to POSITION as it will result in ORDER<-POSITION->ORDER, which
                    // is a loop: first we would fetch the order, than it's positions than again all orders of thouse position, which will result in
                    // that one order we fetched in step 1 again. Not sure, if these loops can be prevented somehow...
                    if (! ($rel->isReverseRelation() && $relation_path_to_new_base_object == $rel->getAliasWithModifier() && ($relation_path_to_new_base_object == $thisStr || $rel->getRightKeyAttribute()->getAlias() == $thisStr))) {
                        $new_expression_string = RelationPath::relationPathAdd($new_expression_string, $thisStr);
                    }
            }
            // If we end up with an empty expression, this means, that the original expression pointed to the exact relation to
            // the object we rebase to. E.g. if we were rebasing ORDER->CUSTOMER->CUSTOMER_CLASS to CUSTOMER, then the relation path given
            // to this method would be ORDER__CUSTOMER__CUSTOMER_CLASS, thus the rebased expression would be empty. However, in this case,
            // we know, that the related_object_key of the last relation was actually ment (probably the UID of the CUSTOMER_CLASS in our
            // example), so we just append it to our empty expression here.
            if ($new_expression_string == '') {
                $new_expression_string .= $rel->getRightKeyAttribute()->getAlias();
            }
            return ExpressionFactory::createFromString($this->getWorkbench(), $new_expression_string, $rel->getRightObject());
        } else {
            // In all other cases (i.e. for constants), just leave the expression as it is. It does not depend on any meta model!
            return $this->copy();
        }
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::getAttribute()
     */
    public function getAttribute()
    {
        if ($this->isMetaAttribute() && $this->getMetaObject()) {
            return $this->getMetaObject()->getAttribute($this->toString());
        } else {
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::getWidgetLink()
     */
    public function getWidgetLink(WidgetInterface $sourceWidget) : WidgetLinkInterface
    {
        if (($link = $this->widgetLinks[$sourceWidget->getId()]) !== null) {
            return $link;
        }
        
        if ($this->widgetLinkString === null) {
            throw new RuntimeException('Cannot get widget linke from expression of type "' . $this->getType() . '"!');
        }
        
        $link = WidgetLinkFactory::createFromWidget($sourceWidget, $this->widgetLinkString);
        $this->widgetLinks[$sourceWidget->getId()] = $link;
        return $link;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     * @return ExpressionInterface
     */
    public function copy()
    {
        $copy = clone $this;
        if ($this->isConstant() === false) {
            $copy->parse($this->originalString);
        }
        return $copy;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::isStatic()
     */
    public function isStatic() : bool
    {
        switch (true) {
            case $this->isConstant():
            case $this->isEmpty():
                return true;
            case $this->isFormula():
                return $this->getFormula()->isStatic();
        }
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::detectFormula()
     */
    public static function detectFormula($value) : bool
    {
        return self::detectCalculation($value) && strpos($value, '(') > 0;
    }
    
     /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::detectCalculation()
     */
    public static function detectCalculation($value) : bool
    {
        if (! is_string($value)) {
            return false;
        }
        $value = trim($value);
        return $value && substr($value, 0, 1) === '=' && substr($value, 1, 1) !== '=';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::detectReference()
     */
    public static function detectReference($value) : bool
    {
        return self::detectCalculation($value) && ! self::detectFormula($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::detectString()
     */
    public static function detectQuotedString($value) : bool
    {
        if ($value === '') {
            return true;
        }
        $value = trim($value);
        $start = substr($value, 0, 1);
        if ($start === '"' || $start === "'") {
            $end = substr($value, -1);
            if ($start === '"' && $end === '"') {
                return true;
            }
            if ($start === "'" && $end === "'") {
                return true;
            }
        }
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ExpressionInterface::detectNumber()
     */
    public static function detectNumber($value) : bool
    {
        return is_numeric($value);
    }
    
    /**
     * 
     * @throws LogicException
     * @return FormulaInterface
     */
    protected function getFormula() : FormulaInterface
    {
        if (! $this->isFormula()) {
            throw new LogicException('Cannot use expression "' . $this->toString() . '" as a formula!');
        }
        
        if ($this->formula === null) {
            $this->formula = $this->parseFormula($this->originalString, $this->relation_path);
        }
        
        return $this->formula;
    }
    
    /**
     * 
     * @param string $quotedString
     * @param string $quote
     * @throws UnexpectedValueException
     * @return string
     */
    protected static function unquote(string $quotedString, string $quote = null) : string
    {
        $quotedString = trim($quotedString);
        
        if ($quotedString === '') {
            return $quotedString;
        }
        
        if ($quote === null) {
            $quote = mb_substr($quotedString, 0, 1);
        }
        
        switch ($quote) {
            case '"':
                return json_decode($quotedString);
            case "'":
                return trim(str_replace("\\'", "'", $quotedString), "'");
        }
        return $quotedString;
    }
}
?>