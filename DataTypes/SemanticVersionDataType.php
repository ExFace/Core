<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use Composer\Semver\VersionParser;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;

/**
 * Semantic version numbers as used by PHP Composer
 * 
 * @author Andrej Kabachnik
 *
 */
class SemanticVersionDataType extends AbstractDataType
{
    const WILDCARD = '*';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::cast()
     */
    public static function cast($string)
    {
        if (SemanticVersionDataType::isValueEmpty($string) === true){
            return $string;
        }

        if (is_float($string) && is_finite($string)) {
            $string = json_encode($string, JSON_PRESERVE_ZERO_FRACTION);
        }
        
        $parser = new VersionParser();
        try {
            // Normalization will add the maximum number of digits to the version,
            // so we just use it for validation without overwriting the string
            // with its notrmalized version
            $parser->normalize($string);
        } catch (\Throwable $e) {
            throw new DataTypeCastingError('"' . $string . '" is not a valid semantic version!', null, $e);
        }
        
        return $string;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($string)
    {
        if ($this::isValueLogicalNull($string)) {
            return $string;
        }
            
        return $this::cast($string);   
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::ASC($this->getWorkbench());
    }
    
    /**
     * Returns TRUE if the given value is a valid semantic version and FALSE otherwise
     * 
     * @param string $value
     * @return bool
     */
    public static function isValueVersion(string $value) : bool
    {
        if (static::isValueEmpty($value) === true || static::isValueLogicalNull($value)) {
            return false;
        }
        
        $parser = new VersionParser();
        try {
            $parser->normalize($value);
        } catch (\Throwable $e) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Returns TRUE if the first value is a later version than the second one and FALSE otherwise
     * 
     * @param string $version
     * @param string $comparedToVersion
     * @return bool
     */
    public static function isVersionGreaterThan(string $version, string $comparedToVersion) : bool
    {
        return Comparator::greaterThan($version, $comparedToVersion);
    }

    /**
     * Increases the major, minor, and patch parts of a semantic version by the given deltas.
     *
     * Pre-release and build metadata suffixes are preserved. Each resulting version part is
     * padded to at least the width of the corresponding original part, so increasing `1.03.01`
     * by one patch produces `1.03.02`. A part may grow beyond its original width if necessary.
     *
     * @param string $version
     * @param mixed $major
     * @param mixed $minor
     * @param mixed $patch
     * @return string
     * @throws DataTypeCastingError
     */
    public static function increment(string $version, $major = 0, $minor = 0, $patch = 0) : string
    {
        static::cast($version);

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)(.*)$/', $version, $matches) !== 1) {
            throw new DataTypeCastingError('"' . $version . '" must have semantic version parts in the format MAJOR.MINOR.PATCH!');
        }

        $deltas = [
            static::parseIncrementDelta($major, 'major'),
            static::parseIncrementDelta($minor, 'minor'),
            static::parseIncrementDelta($patch, 'patch')
        ];
        $parts = [];
        for ($index = 0; $index < 3; $index++) {
            $originalPart = $matches[$index + 1];
            $newPart = (int) $originalPart + $deltas[$index];
            if ($newPart < 0) {
                throw new DataTypeCastingError('Semantic version parts must not be negative!');
            }
            $parts[] = str_pad((string) $newPart, strlen($originalPart), '0', STR_PAD_LEFT);
        }

        return implode('.', $parts) . ($matches[4] ?? '');
    }

    /**
     * Parses a semantic version increment as an integer.
     *
     * @param mixed $value
     * @param string $name
     * @return int
     * @throws DataTypeCastingError
     */
    protected static function parseIncrementDelta($value, string $name) : int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (preg_match('/^-?\d+$/', (string) $value) !== 1) {
            throw new DataTypeCastingError('"' . $value . '" is not a valid ' . $name . ' version increment!');
        }

        return (int) $value;
    }
    
    /**
     * 
     * @param string $constraint
     * @param array $versions
     * @return string|NULL
     */
    public static function findVersionBest(string $constraint, array $versions) : ?string
    {
        $satisfying = static::findVersionsSatisfying($constraint, $versions);
        $satisfying = static::sort($satisfying);
        return $satisfying[0] ?? null;
    }
    
    /**
     * 
     * @param string $constraint
     * @param string[] $versions
     * @return string[]
     */
    public static function findVersionsSatisfying(string $constraint, array $versions) : array
    {
        return Semver::satisfiedBy($versions, $constraint);
    }
    
    /**
     * 
     * @param string[] $versions
     * @param string $direction
     * @return string[]
     */
    public static function sort(array $versions, string $direction = SortingDirectionsDataType::DESC) : array
    {
        $direction = SortingDirectionsDataType::cast($direction);
        if ($direction === SortingDirectionsDataType::ASC) {
            $sorted = Semver::sort($versions);
        } else {
            $sorted = Semver::rsort($versions);
        }
        return $sorted;
    }
}