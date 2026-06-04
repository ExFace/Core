<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\FormulaError;

/**
 * Turns JSON arrays into delimited lists.
 *
 * If no delimiter is provided, the formula uses the global list delimiter `EXF_LIST_SEPARATOR`.
 * If no JSONPath is provided, the top-level JSON array (or top-level object values) is converted.
 *
 * ## Examples
 *
 * - `=JsonList('["a","b","c"]')` -> `a,b,c` (assuming `EXF_LIST_SEPARATOR` is `,`)
 * - `=JsonList('["a","b","c"]', '; ')` -> `a; b; c`
 * - `=JsonList('{"tags":["a","b"]}', ', ', '$.tags[*]')` -> `a, b`
 * - `=JsonList('{"a":1,"b":2}')` -> `1,2` (assuming `EXF_LIST_SEPARATOR` is `,`)
 *
 * @author Andrej Kabachnik
 */
class JsonList extends Formula
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $json = null, string $delimiter = null, string $jsonPath = null)
    {
        if ($json === null || $json === '') {
            return '';
        }

        $delimiter = ($delimiter === null || $delimiter === '') ? EXF_LIST_SEPARATOR : $delimiter;

        try {
            $decoded = JsonDataType::decodeJson($json);

            if ($jsonPath === null || $jsonPath === '') {
                if (is_array($decoded)) {
                    // For top-level objects, list values instead of keys.
                    $values = ArrayDataType::isAssociative($decoded) ? array_values($decoded) : $decoded;
                } else {
                    $values = [$decoded];
                }
            } else {
                $values = ArrayDataType::filterJsonPath($decoded, $jsonPath) ?? [];
            }

            $listValues = [];
            foreach ($values as $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                if (is_array($value) || is_object($value)) {
                    $listValues[] = JsonDataType::encodeJson($value);
                    continue;
                }

                if (is_bool($value)) {
                    $listValues[] = $value ? 'true' : 'false';
                    continue;
                }

                $listValues[] = (string) $value;
            }

            return implode($delimiter, $listValues);
        } catch (\Throwable $e) {
            throw new FormulaError($this, 'Cannot evaluate formula =JsonList(): ' . $e->getMessage(), null, $e, [$json, $delimiter, $jsonPath]);
        }
    }
}