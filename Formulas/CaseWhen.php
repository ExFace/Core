<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\FormulaError;

/**
 * Case-when/switch style branching formula: `=Case(<condition>,<value>,<condition>,<value>,...)`.
 * 
 * Expects alternating condition/value arguments with an optional
 * `default, value` pair as fallback. The `default` marker is case-insensitive and must be a string.
 * Pairs are evaluated from left to right, and the first condition evaluating
 * to `TRUE` returns its value. If possible, use newlines for better readability:
 * 
 * ```
 *  =Case(
 *      STATUS == 'Open', 'In progress',
 *      STATUS == 'Done', 'Completed',
 *      'DEFAULT', 'New'
 *  )
 * 
 * ```
 * 
 * Examples:
 * 
 * - `=Case(STATUS == 'Open', 'In progress', STATUS == 'Done', 'Completed')`
 * - `=Case(SCORE >= 90, 'A', SCORE >= 80, 'B', 'default', 'C')`
 * - `=CaseWhen(IS_ACTIVE, 'Active', 'default', 'Inactive')`
 * 
 * Throws an error if:
 * - No condition could be matched, and no default was provided.
 * - An uneven number of arguments was provided.
 * - More than one default argument was provided.
 */
class CaseWhen extends Formula
{
    public function run(...$args)
    {
        $argCount = count($args);
        if ($argCount < 2 || $argCount % 2 !== 0) {
            throw new FormulaError($this, 'Case() expects alternating [condition, value] arguments.');
        }

        $hasDefault = false;
        $defaultValue = null;

        for ($i = 0; $i < $argCount; $i += 2) {
            $condition = $args[$i];
            $value = $args[$i + 1];

            if (is_string($condition) && mb_strtolower(trim($condition)) === 'default') {
                if($hasDefault) {
                    throw new FormulaError($this, 'Case() only supports one `default` argument.');
                }
                
                $hasDefault = true;
                $defaultValue = $value;
                continue;
            }

            $isTrue = ($condition === null || $condition === '') ? false : BooleanDataType::cast($condition);
            if ($isTrue === true) {
                return $value;
            }
        }

        if ($hasDefault) {
            return $defaultValue;
        }

        throw new FormulaError($this, 'Case() has no matching TRUE condition and no default fallback.');
    }
}


