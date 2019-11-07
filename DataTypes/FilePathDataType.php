<?php
namespace exface\Core\DataTypes;

use Webmozart\PathUtil\Path;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

/**
 * Data type for file paths (file system agnostic).
 * 
 * @author Andrej Kabachnik
 *
 */
class FilePathDataType extends StringDataType
{
    private $basePath = null;
    
    private $extension = null;
    
    /**
     *
     * @return string|NULL
     */
    public function getBasePath() : ?string
    {
        return $this->basePath;
    }
    
    /**
     * Adds a base to every path.
     *
     * Use this if your data only includes a relative path. You can prefix
     * it then with an absolute or relative base. This will not change the value,
     * but will tell widgets and other components to use this base automatically.
     *
     * @uxon-property base_path
     * @uxon-type uri
     *
     * @param string $value
     * @return UrlDataType
     */
    public function setBasePath(string $value) : UrlDataType
    {
        $this->basePath = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getExtension() : ?string
    {
        return $this->extension;
    }
    
    /**
     * Extension the path must have.
     * 
     * @uxon-property extension
     * @uxon-type string
     * 
     * @param string $value
     * @return FilePathDataType
     */
    public function setExtension(string $value) : FilePathDataType
    {
        $this->extension = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::parse()
     */
    public function parse($value)
    {
        $value = parent::parse($value);  
        
        if ($ext = $this->getExtension()) {
            if (strcasecmp($ext, static::findExtension($value)) !== 0) {
                throw new DataTypeValidationError($this, 'Invalid value "' . $value . '" for file path: extension "' . static::findExtension($value) . '" does not match required extension "' . $ext . '"!');
            }
        }
        
        return $value;
    }
    
    /**
     * Transforms "C:\wamp\www\exface\exface\vendor\exface\Core\CommonLogic\..\..\..\.." to "C:/wamp/www/exface/exface"
     *
     * @param string $path
     * @return string
     */
    public static function normalize($path, $directory_separator = '/') : string
    {
        $path = Path::canonicalize($path);
        if ($directory_separator !== '/') {
            $path = str_replace('/', $directory_separator, $path);
        }
        return $path;
    }
    
    /**
     * Returns TRUE if the given string is an absolute path and FALSE otherwise
     *
     * @param string|null $path
     * @return boolean
     */
    public static function isAbsolute($path) : bool
    {
        if (is_null($path) || $path == '') {
            return false;
        }
        return Path::isAbsolute($path);
    }
    
    /**
     * Returns TRUE if the given string is a relative path and FALSE otherwise
     *
     * @param string|null $path
     * @return boolean
     */
    public function isRelative($path) : bool
    {
        return self::isAbsolute($path) === false;
    }
    
    /**
     * Joins all paths given in the array and returns the resulting path
     *
     * @param array $paths
     * @return string
     */
    public static function join(array $paths) : string
    {
        return Path::join($paths);
    }
    
    /**
     * Returns the longest common base path for all given paths or NULL if there is no common base.
     *
     * @param array $paths
     * @return string|NULL
     */
    public static function findCommonBase(array $paths) : ?string
    {
        return Path::getLongestCommonBasePath($paths);
    }
    
    public static function findExtension($path) : ?string
    {
        return Path::getExtension($path);
    }
}