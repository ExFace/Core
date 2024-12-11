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