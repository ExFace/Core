<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * A scalar range with two endpoints separated by the standard BETWEEN delimiter.
 * 
 * Set `endpoint_data_type` to parse and format both bounds using another data type.
 * The following configuration displays `2026-11-01..2026-11-30` as
 * `01.11.2026 .. 30.11.2026` without changing the normalized value:
 * 
 * ```json
 * {
 *   "endpoint_data_type": {
 *     "alias": "exface.Core.Date",
 *     "format": "dd.MM.yyyy"
 *   }
 * }
 * 
 * ```
 * 
 */
class RangeDataType extends StringDataType
{
    private ?DataTypeInterface $endpointDataType = null;
    private UxonObject|string|null $endpointDataTypeConfig = null;

    /**
     * Optional data type of both endpoints, specified by alias or a nested data type configuration.
     * 
     * @uxon-property endpoint_data_type
     * @uxon-type \exface\Core\CommonLogic\DataTypes\AbstractDataType
     * @uxon-template {"alias": ""}
     */
    public function setEndpointDataType(DataTypeInterface|UxonObject|string $dataType) : self
    {
        $config = null;
        if ($dataType instanceof UxonObject) {
            if (! $dataType->getProperty('alias')) {
                throw new DataTypeConfigurationError($this, 'An endpoint data type configuration must specify an alias.');
            }
            $config = $dataType->copy();
            $dataType = DataTypeFactory::createFromUxon($this->getWorkbench(), $dataType);
        } elseif (is_string($dataType)) {
            $config = $dataType;
            $dataType = DataTypeFactory::createFromString($this->getWorkbench(), $dataType);
        }
        $this->endpointDataType = $dataType;
        $this->endpointDataTypeConfig = $config;
        return $this;
    }

    /**
     * Returns the type shared by both bounds, or null for untyped bounds.
     */
    public function getEndpointDataType() : ?DataTypeInterface
    {
        return $this->endpointDataType;
    }

    /**
     * Exports the configuration, including the type and options of the bounds.
     */
    public function exportUxonObject() : UxonObject
    {
        $uxon = parent::exportUxonObject();
        if ($this->endpointDataType !== null) {
            // Some data types do not export all their options. Preserve the supplied UXON.
            $config = $this->endpointDataTypeConfig ?? $this->endpointDataType->exportUxonObject()
                ->setProperty('alias', $this->endpointDataType->getSelector()->toString());
            $uxon->setProperty('endpoint_data_type', $config);
        }
        return $uxon;
    }

    /**
     * Validates the structure of a closed range without interpreting its bounds.
     *
     * @throws DataTypeCastingError
     */
    public static function cast($value) : ?string
    {
        if (self::isUnsetRange($value)) {
            return $value;
        }

        return self::joinBounds(self::splitBounds($value));
    }

    /**
     * Normalizes a closed range to `from..to`, preserving empty and logical-null values.
     *
     * @throws DataTypeCastingError
     */
    public function parse($value)
    {
        // Keep an unset or logical-null value distinct from a malformed range.
        if (self::isUnsetRange($value)) {
            return $value === '' && $this->getForceNullForEmptyValues() ? null : $value;
        }

        return implode(ComparatorDataType::BETWEEN, $this->parseBounds($value));
    }

    /**
     * Formats both bounds for display without changing the normalized value.
     *
     * {@inheritDoc}
     */
    public function format($value = null, bool $silent = true) : string
    {
        $value = $value ?? $this->getValue();
        if (self::isUnsetRange($value)) {
            return '';
        }

        try {
            $bounds = $this->parseBounds($value);
            if ($this->endpointDataType !== null) {
                $bounds = array_map(fn($bound) => $this->endpointDataType->format($bound, false), $bounds);
            }
            return implode(' ' . ComparatorDataType::BETWEEN . ' ', $bounds);
        } catch (DataTypeCastingError | DataTypeExceptionInterface $e) {
            $input = is_scalar($value) ? (string) $value : get_debug_type($value);
            $error = $this->createFormatterError($input, $e);
            if (! $silent) {
                throw $error;
            }
            $this->getWorkbench()->getLogger()->logException($error, LoggerInterface::WARNING);
            return is_scalar($value) ? $input : '';
        }
    }

    private function parseBounds($value) : array
    {
        // Preserve the casting error contract before the parent parser wraps casting errors.
        $range = static::cast($value);
        $this->validateRangeLength($range, $value);
        $bounds = self::splitBounds(parent::parse($range));
        if ($this->endpointDataType !== null) {
            try {
                $bounds = array_map(function ($bound) {
                    $parsed = $this->endpointDataType->parse($bound);
                    if (! is_scalar($parsed)) {
                        throw new DataTypeCastingError('Both range endpoints must have a scalar value.');
                    }
                    return is_bool($parsed) ? ($parsed ? '1' : '0') : (string) $parsed;
                }, $bounds);
                $normalized = self::joinBounds($bounds);
            } catch (DataTypeCastingError $e) {
                throw $this->createValidationParseError($value, null, null, null, $e);
            }
            $this->validateRangeLength($normalized, $value);
        }
        return $bounds;
    }

    private function validateRangeLength(string $range, $value) : void
    {
        $maxLength = $this->getLengthMax();
        if ($maxLength && mb_strlen($range) > $maxLength) {
            throw $this->createValidationRuleError($value, 'The range exceeds the maximum length.');
        }
    }

    private static function isUnsetRange($value) : bool
    {
        return static::isValueEmpty($value) || (is_string($value) && static::isValueLogicalNull($value));
    }

    private static function splitBounds($value) : array
    {
        if (! is_string($value)) {
            throw new DataTypeCastingError('Expected a range string in the format from..to.');
        }
        $bounds = array_map('trim', explode(ComparatorDataType::BETWEEN, $value));
        if (count($bounds) !== 2 || in_array('', $bounds, true) || str_contains($value, ComparatorDataType::BETWEEN . '.')) {
            throw new DataTypeCastingError('Expected a range with two endpoints in the format from..to.');
        }
        return $bounds;
    }

    private static function joinBounds(array $bounds) : string
    {
        $range = implode(ComparatorDataType::BETWEEN, $bounds);
        if (self::splitBounds($range) !== $bounds) {
            throw new DataTypeCastingError('The endpoints cannot be represented unambiguously in the format from..to.');
        }
        return $range;
    }
}
