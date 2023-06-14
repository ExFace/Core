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
     * @uxon-type string
     *
     * @param string $value
     * @return FilePathDataType
     */
    public function setBasePath(string $value) : FilePathDataType
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
    public static function isRelative($path) : bool
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
    
    /**
     * Returns the file extension from the given path: e.g. my/folder/file.txt => .txt
     * 
     * @param string $path
     * @return string|NULL
     */
    public static function findExtension(string $path) : ?string
    {
        return Path::getExtension($path);
    }
    
    /**
     * Returns the file name from the given path: e.g. my/folder/file.txt => file
     * 
     * Set $includeExtension to true to get file.txt in the above example.
     * 
     * @param string $path
     * @param bool $includeExtension
     * @return string
     */
    public static function findFileName(string $path, bool $includeExtension = false) : string
    {
        if ($includeExtension === false) {
            return pathinfo($path, PATHINFO_FILENAME);
        } else {
            return pathinfo($path, PATHINFO_BASENAME);
        }
    }
    
    /**
     * Returns the folder path from the given path: e.g. my/folder/file.txt => my/folder
     * 
     * @param string $path
     * @return string
     */
    public static function findFolderPath(string $path) : string
    {
        return pathinfo($path, PATHINFO_DIRNAME); 
    }
    
    /**
     * Returns the folder name from the given path: e.g. my/folder/file.txt => folder
     *
     * @param string $path
     * @return string
     */
    public static function findFolder(string $path) : string
    {
        return pathinfo(pathinfo($path, PATHINFO_DIRNAME), PATHINFO_FILENAME);
    }
    
    /**
     * Removes not allowed characters in a filename
     * https://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
     * 
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename(string $filename) {
        // sanitize filename
        $filename = preg_replace(
            '~
        [<>:"/\\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved https://www.rfc-editor.org/rfc/rfc3986#section-2.2
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        ~x',
            '-', $filename);
        // avoids ".", ".." or ".hiddenFiles"
        $filename = ltrim($filename, '.-');
        // maximize filename length to 255 bytes http://serverfault.com/a/9548/44086
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');
        return $filename;
    }
    
    /**
     * Returns TRUE if $path matches the $pattern with wildcards
     * 
     * Technically this methods works the same as the built-in PHP `fnmatch()`, but
     * it also works on non-POSIX systems, whereas `fnmatch()` does not.
     * 
     * Examples:
     * 
     * - `matchesPattern('folder/*.*', 'folder/asdf.jpg')` => true 
     * 
     * @param string $path
     * @param string $pattern
     * @param int $fnmatchFlags
     * @return bool
     */
    public static function matchesPattern(string $path, string $pattern, int $fnmatchFlags = 0) : bool
    {
        if (! function_exists('fnmatch')) {
            return static::fnmatchPolyfill($pattern, $path);
        }
        return fnmatch($pattern, $path, $fnmatchFlags);
    }
    
    /**
     * Polyfill for PHP fnmatch() in case it is not available
     * 
     * @link https://www.php.net/fnmatch
     * 
     * @param string $pattern
     * @param string $string
     * @param int $flags
     * @return boolean
     */
    protected static function fnmatchPolyfill($pattern, $string, $flags = 0) {
        if (!function_exists('fnmatch')) {
            define('FNM_PATHNAME', 1);
            define('FNM_NOESCAPE', 2);
            define('FNM_PERIOD', 4);
            define('FNM_CASEFOLD', 16);
        }
        
        $modifiers = null;
        $transforms = array(
            '\*'    => '.*',
            '\?'    => '.',
            '\[\!'    => '[^',
            '\['    => '[',
            '\]'    => ']',
            '\.'    => '\.',
            '\\'    => '\\\\'
        );
        
        // Forward slash in string must be in pattern:
        if ($flags & FNM_PATHNAME) {
            $transforms['\*'] = '[^/]*';
        }
        
        // Back slash should not be escaped:
        if ($flags & FNM_NOESCAPE) {
            unset($transforms['\\']);
        }
        
        // Perform case insensitive match:
        if ($flags & FNM_CASEFOLD) {
            $modifiers .= 'i';
        }
        
        // Period at start must be the same as pattern:
        if ($flags & FNM_PERIOD) {
            if (strpos($string, '.') === 0 && strpos($pattern, '.') !== 0) return false;
        }
        
        $pattern = '#^'
            . strtr(preg_quote($pattern, '#'), $transforms)
            . '$#'
                . $modifiers;
                
                return (boolean)preg_match($pattern, $string);
    }
}