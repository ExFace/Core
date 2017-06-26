<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\FormulaFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Exceptions\Model\ExpressionRebaseImpossibleError;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeCopied;

class Expression implements ExfaceClassInterface, iCanBeCopied
{

    // Expression types
    // const FORMULA = 'formula';
    // const ATTRIBUTE = 'attribute';
    // const STRING = 'string';
    private $attributes = array();

    private $formula = null;

    private $widget_link = null;

    private $attribute_alias = null;

    private $value = null;

    private $type = null;

    private $relation_path = '';

    private $string = '';

    private $data_type = null;

    private $exface;

    private $meta_object = null;

    function __construct(\exface\Core\CommonLogic\Workbench $exface, $string, $meta_object = null)
    {
        $this->exface = $exface;
        $this->meta_object = $meta_object;
        $this->parse($string);
        $this->string = $string;
    }

    /**
     * Parses an ExFace expression and returns it's type
     *
     * @param
     *            string expression
     */
    function parse($expression)
    {
        $expression = trim($expression);
        // see, what type of expression it is. Depending on the type, the evaluate() method will give different results.
        $str = $this->parseQuotedString($expression);
        if (! $expression || $str !== false) {
            $this->type = 'string';
            $this->value = $str;
        } elseif (strpos($expression, '=') === 0) {
            if (strpos($expression, '(') !== false && strpos($expression, ')') !== false) {
                $this->type = 'formula';
                $this->formula = $this->parseFormula($expression);
                $this->attributes = array_merge($this->attributes, $this->formula->getRequiredAttributes());
            } else {
                $this->type = 'reference';
                $this->widget_link = WidgetLinkFactory::createFromAnything($this->exface, substr($expression, 1));
            }
        } else { // attribute_alias
            if (! $this->getMetaObject() || ($this->getMetaObject() && $this->getMetaObject()->hasAttribute($expression))) {
                $this->type = 'attribute_alias';
                $this->attribute_alias = $expression;
                $this->attributes[] = $expression;
            } else {
                $this->type = 'string';
                $this->value = $str === false ? '' : $str;
            }
        }
        
        return $this->getType();
    }

    function isMetaAttribute()
    {
        if ($this->type == 'attribute_alias')
            return true;
        else
            return false;
    }

    function isFormula()
    {
        if ($this->type == 'formula')
            return true;
        else
            return false;
    }

    function isString()
    {
        if ($this->type == 'string')
            return true;
        else
            return false;
    }

    /**
     * Returns TRUE if the expression has no value (expression->toString() = NULL) and FALSE otherwise
     *
     * @return boolean
     */
    function isEmpty()
    {
        return is_null($this->toString()) ? true : false;
    }

    function isReference()
    {
        if ($this->type == 'reference')
            return true;
        else
            return false;
    }

    function parseQuotedString($expression)
    {
        if (substr($expression, 0, 1) == '"' || substr($expression, 0, 1) == "'") {
            return trim($expression, '"\'');
        } else {
            return false;
        }
    }

    /**
     * Checks, if the given expression is a data function and returns the function object if so, false otherwise.
     * It is a good idea to create the function here already, because we need to know it's required attributes.
     *
     * @param string $expression            
     * @return boolean|exf_formula function object or false
     */
    function parseFormula($expression)
    {
        if (substr($expression, 0, 1) !== '=')
            return false;
        $expression = substr($expression, 1);
        $parenthesis_1 = strpos($expression, '(');
        $parenthesis_2 = strrpos($expression, ')');
        
        if ($parenthesis_1 === false || $parenthesis_2 === false) {
            throw new FormulaError('Syntax error in the data function: "' . $expression . '"');
        }
        
        $func_name = substr($expression, 0, $parenthesis_1);
        $params = substr($expression, $parenthesis_1 + 1, $parenthesis_2 - $parenthesis_1 - 1);
        
        return FormulaFactory::createFromString($this->exface, $func_name, $this->parseParams($params));
    }

    protected function parseParams($str)
    {
        $buffer = '';
        $stack = array();
        $depth = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i ++) {
            $char = $str[$i];
            switch ($char) {
                case '(':
                    $depth ++;
                    break;
                case ',':
                    if (! $depth) {
                        if ($buffer !== '') {
                            $stack[] = $buffer;
                            $buffer = '';
                        }
                        continue 2;
                    }
                    break;
                case ' ':
                    if (! $depth) {
                        // Not sure, what the purpose of this continue is, but it removes whitespaces from formual arguments in the first level
                        // causing many problems. Commented it out for now to see if that helps.
                        // continue 2;
                    }
                    break;
                case ')':
                    if ($depth) {
                        $depth --;
                    } else {
                        $stack[] = $buffer . $char;
                        $buffer = '';
                        continue 2;
                    }
                    break;
            }
            $buffer .= $char;
        }
        if ($buffer !== '') {
            $stack[] = $buffer;
        }
        
        return $stack;
    }

    /**
     * Evaluates the given expression based on a data sheet and the coordinates of a cell.
     * Returns either a string value (if column and row are specified) or an array of values (if only the column is specified).
     *
     * @param
     *            \exface\Core\Interfaces\DataSheets\DataSheetInterface data_sheet
     * @param
     *            string column_name
     * @param
     *            int row_number
     * @return array|string
     */
    function evaluate(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $column_name, $row_number = null)
    {
        if (is_null($row_number)) {
            $result = array();
            $rows_and_totals_count = $data_sheet->countRowsLoaded() + count($data_sheet->getTotalsRows());
            for ($r = 0; $r < $rows_and_totals_count; $r ++) {
                $result[] = $this->evaluate($data_sheet, $column_name, $r);
            }
            return $result;
        }
        switch ($this->type) {
            case 'attribute_alias':
                return $data_sheet->getCellValue($this->attribute_alias, $row_number);
            case 'formula':
                return $this->formula->evaluate($data_sheet, $column_name, $row_number);
            default:
                return $this->value;
        }
    }

    function getRequiredAttributes()
    {
        return $this->attributes;
    }

    function getType()
    {
        return $this->type;
    }

    public function getRelationPath()
    {
        return $this->relation_path;
    }

    public function setRelationPath($relation_path)
    {
        // remove old relation path
        if ($this->relation_path) {
            $path_length = strlen($this->relation_path . RelationPath::RELATION_SEPARATOR);
            foreach ($this->attributes as $key => $a) {
                $this->attributes[$key] = substr($a, $path_length);
            }
        }
        
        // set new relation path
        $this->relation_path = $relation_path;
        if ($relation_path) {
            foreach ($this->attributes as $key => $a) {
                $this->attributes[$key] = $relation_path . RelationPath::RELATION_SEPARATOR . $a;
            }
        }
        
        if ($this->formula)
            $this->formula->setRelationPath($relation_path);
        if ($this->attribute_alias)
            $this->attribute_alias = $relation_path . RelationPath::RELATION_SEPARATOR . $this->attribute_alias;
    }

    /**
     * Returns the expression as string.
     * Basically this is the opposite fo parse.
     * Note, that in case of attributes the expression will include the relation path, aggregators, etc., whereas getAttribute->getAlias() would return only the actual alias.
     *
     * @return string
     */
    function toString()
    {
        return $this->string;
    }

    function getRawValue()
    {
        return $this->value;
    }

    function getWorkbench()
    {
        return $this->exface;
    }

    public function getDataType()
    {
        if (is_null($this->data_type)) {
            switch ($this->type) {
                case 'formula':
                    $this->data_type = $this->formula->getDataType();
                    break;
                case 'attribute_alias':
                    // FIXME How to get the attribute by alias, if we do not know the object here???
                    break;
                case 'string':
                    $this->data_type = DataTypeFactory::createFromAlias($this->exface, EXF_DATA_TYPE_STRING);
                    break;
                default:
                    $this->data_type = DataTypeFactory::createFromAlias($this->exface, EXF_DATA_TYPE_STRING);
            }
        }
        return $this->data_type;
    }

    public function setDataType($value)
    {
        $this->data_type = $value;
    }

    public function mapAttribute($map_from, $map_to)
    {
        foreach ($this->attributes as $id => $attr) {
            if ($attr == $map_from) {
                $this->attributes[$id] = $map_to;
            }
        }
        if ($this->formula)
            $this->formula->mapAttribute($map_from, $map_to);
    }

    public function getMetaObject()
    {
        return $this->meta_object;
    }

    public function setMetaObject(Object $object)
    {
        $this->meta_object = $object;
    }

    /**
     * Returns the same expression, but relative to another base object.
     * E.g. "ORDER->POSITION->PRODUCT->ID" will become "PRODUCT->ID" after calling rebase(ORDER->POSITION) on it.
     *
     * @param string $relation_path_to_new_base_object            
     * @return expression
     */
    public function rebase($relation_path_to_new_base_object)
    {
        if ($this->isFormula()) {
            // TODO Implement rebasing formulas. It should be possible via recursion.
            return $this;
        } elseif ($this->isMetaAttribute()) {
            try {
                $rel = $this->getMetaObject()->getRelation($relation_path_to_new_base_object);
            } catch (MetaRelationNotFoundError $e) {
                throw new ExpressionRebaseImpossibleError('Cannot rebase expression "' . $this->toString() . '" relative to "' . $relation_path_to_new_base_object . '" - invalid relation path given!', '6TBX1V2');
            }
            
            if (strpos($this->toString(), $relation_path_to_new_base_object) === 0) {
                // If the realtion path to the new object is just the beginning of the expression, cut it off, returning whatever is left
                // $new_expression_string = RelationPath::relaton_path_cut($this->toString(), $relation_path_to_new_base_object);
                $new_expression_string = ltrim(substr($this->toString(), strlen($relation_path_to_new_base_object)), "_");
            } elseif (strpos($relation_path_to_new_base_object, $this->toString()) === 0) {
                // If the expression is the beginning of the relation path, do it the other way around
                // $new_expression_string = RelationPath::relaton_path_cut($relation_path_to_new_base_object, $this->toString());
                $new_expression_string = ltrim(substr($relation_path_to_new_base_object, strlen($this->toString())), "_");
            } else {
                // Otherwise append the expression to the relation path (typically the expression is a direct attribute here an would need
                // a relation path, if referenced from another object).
                $new_expression_string = RelationPath::relationPathReverse($relation_path_to_new_base_object, $this->getMetaObject());
                // Pay attention to reverse relations though: if the expression is the key of the main_object_key of the relation,
                // we don't need to append it. The related_object_key (foreign key) will suffice. That is, if we need to rebase the reverse
                // relation POSITION of the the object ORDER relative to that object, we will get ORDER (because POSITION->ORDER ist the
                // opposite of ORDER<-POSITION). Rebasing POSITION->ORDER->UID from ORDER to POSITION will yield ORDER->UID though because
                // the UID attribute is explicitly referenced here.
                // IDEA A bit awqard is rebasing "POSITION->ORDER" from ORDER to POSITION as it will result in ORDER<-POSITION->ORDER, which
                // is a loop: first we would fetch the order, than it's positions than again all orders of thouse position, which will result in
                // that one order we fetched in step 1 again. Not sure, if these loops can be prevented somehow...
                if (! ($rel->isReverseRelation() && $relation_path_to_new_base_object == $rel->getAlias() && ($relation_path_to_new_base_object == $this->toString() || $rel->getRelatedObjectKeyAlias() == $this->toString()))) {
                    $new_expression_string = RelationPath::relationPathAdd($new_expression_string, $this->toString());
                }
            }
            // If we end up with an empty expression, this means, that the original expression pointed to the exact relation to
            // the object we rebase to. E.g. if we were rebasing ORDER->CUSTOMER->CUSTOMER_CLASS to CUSTOMER, then the relation path given
            // to this method would be ORDER__CUSTOMER__CUSTOMER_CLASS, thus the rebased expression would be empty. However, in this case,
            // we know, that the related_object_key of the last relation was actually ment (probably the UID of the CUSTOMER_CLASS in our
            // example), so we just append it to our empty expression here.
            if ($new_expression_string == '') {
                $new_expression_string .= $rel->getRelatedObjectKeyAlias();
            }
            
            return $this->getWorkbench()->model()->parseExpression($new_expression_string, $rel->getRelatedObject());
        } else {
            // In all other cases (i.e. for constants), just leave the expression as it is. It does not depend on any meta model!
            return $this;
        }
    }

    /**
     * Returns the meta attribute, represented by this expression or FALSE if the expression represents something else (a formula, a constant, etc.)
     *
     * @return boolean|attribute
     */
    public function getAttribute()
    {
        if ($this->isMetaAttribute() && $this->getMetaObject()) {
            return $this->getMetaObject()->getAttribute($this->toString());
        } else {
            return false;
        }
    }

    public function getWidgetLink()
    {
        return $this->widget_link;
    }

    public function copy()
    {
        $copy = clone $this;
        $copy->parse($this->toString());
        return $copy;
    }
}
?>