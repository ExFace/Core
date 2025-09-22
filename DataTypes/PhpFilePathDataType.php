<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Data type for PHP file paths (file system agnostic).
 * 
 * In addition to the regular `FilePathDataType`, this one knows of the PHP extensions and
 * is able to get PHP classes from files.
 * 
 * @author Andrej Kabachnik
 *
 */
class PhpFilePathDataType extends FilePathDataType
{
    const FILE_EXTENSION_PHP = 'php';
    
    private static array $cachedFileClasses = [];
    private static array $cachedAppFolders = [];
    private static array $cachedAppAliases = [];
    private static array $cachedNamespaces = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\FilePathDataType::getExtension()
     */
    public function getExtension() : ?string
    {
        $ext = parent::getExtension();
        if ($ext === null) {
            return self::FILE_EXTENSION_PHP;
        }
        return $ext;
    }

    /**
     * Finds the qualified class name (with namespace) if the given file - even case insensitive!
     * 
     * This method is important to load file path selectors reliably. Depending on the file systems where
     * the apps are run and developed, the class namespaces and file paths may differ in case. This
     * produces different issues on Windows and on Unix/Linux
     * 
     * Examples:
     * 
     * - `/exface/Core/Behaviors/TimeStampingBehavior.php` results in 
     *      - real path: `/exface/core/Behaviors/TimeStampingBehavior.php`
     *      - class: `\exface\Core\Behaviors\TimeStampingBehavior`
     * 
     * @param WorkbenchInterface $workbench
     * @param string $pathRelOrAbs
     * @return string
     */
    public static function findClassInVendorFile(WorkbenchInterface $workbench, string $pathRelOrAbs) : string
    {
        $triedClasses = [];
        $dirSep = FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR;
        $string = Filemanager::pathNormalize($pathRelOrAbs, $dirSep);
        $vendorFolder = Filemanager::pathNormalize($workbench->filemanager()->getPathToVendorFolder(), $dirSep);

        // Calculate relative path as /exface/Core/Behaviors/TimeStampingBehavior.php
        // And absolute path
        if (StringDataType::startsWith($string, $vendorFolder . $dirSep)) {
            $relPath = mb_substr($string, mb_strlen($vendorFolder));
            $absPath = $string;
        } else {
            $relPath = $dirSep . ltrim($string, $dirSep);
            $absPath = $vendorFolder . $relPath;
        }
        
        // Look in cache first
        if (null !== $cache = (static::$cachedFileClasses[mb_strtoupper($relPath)] ?? null)) {
            return $cache;
        }

        // We can be sure, the class name is the file name exactly
        $className = FilePathDataType::findFileName($relPath);
        // But the folder path can have case differences - especially in the composer package part (first two levels)
        $folderPath = FilePathDataType::findFolderPath($relPath);
        
        // Check the namespace cache. If found, we can use the namespace for our class name
        if (null !== $cache = (static::$cachedNamespaces[mb_strtoupper($folderPath)] ?? null)) {
            $class = $cache . '\\' . $className;
            if (class_exists($class)) {
                static::$cachedFileClasses[mb_strtoupper($relPath)] = $class;
                return $class;
            } else {
                $triedClasses[] = $class;
            }
        }
        
        // If not cached, try to determine the namespace
        list($appVendor, $appAlias, $pathInApp) = explode($dirSep, ltrim($folderPath, $dirSep), 3);
        $appFolder = $appVendor . $dirSep . $appAlias;
        $nsInApp = str_replace($dirSep, '\\', $pathInApp);
        
        // If app namespace is cached already, we should be able to guess the class already
        if (null !== $appNs = (self::$cachedNamespaces[mb_strtoupper($appFolder)] ?? null)) {
            $class = $appNs . '\\' . $nsInApp . '\\' . $className;
            if (class_exists($class)) {
                static::$cachedFileClasses[mb_strtoupper($relPath)] = $class;
                return $class;
            }
        }

        // If we do not know the app namespace or did not find the class right away
        if (! file_exists($absPath)) {
            // See if we already know the real app folder. If not, find it and cache it
            if (null === $appFolderReal = (self::$cachedAppFolders[mb_strtoupper($appFolder)] ?? null)) {
                $appFolderReal = $workbench->getAppFolder($appVendor . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $appAlias);
                self::$cachedAppFolders[mb_strtoupper($appFolder)] = $appFolderReal;
            }
            $relPath = FilePathDataType::findPathCaseInsensitive($relPath, $vendorFolder, $dirSep);
            $absPath = $vendorFolder . $dirSep . $relPath;
            $folderPath = FilePathDataType::findFolderPath($relPath);
        }

        $e = null;
        try {
            // The namespace can be different, then the file path, so get it 
            // directly from the path. Of course, we could fetch the entire class
            // name from the file, but this is way slower because it requires
            // tokenizing.
            $namespace = PhpFilePathDataType::findNamespaceOfFile($absPath);
            static::$cachedNamespaces[mb_strtoupper($folderPath)] = $namespace;
            $class = $namespace . '\\' . $className;
            $triedClasses[] = $class;
            if (class_exists($class)) {
                static::$cachedFileClasses[mb_strtoupper($relPath)] = $class;
            } else {
                $class = static::findClassInFile($absPath);
                $triedClasses[] = $class;
                static::$cachedFileClasses[mb_strtoupper($relPath)] = $class;
            }
            return $class;
        } catch (\Throwable $e) {
            // Just keep $e here for the final exception below
        }
        throw new FileNotFoundError('Cannot load class from "' . $pathRelOrAbs . '". Tried "' . implode('", "', $triedClasses) . '".', null, $e);
    }
    
    /**
     * Returns the qualified class name of the first class defined in the file.
     *
     * If a PSR-compatible class exists, it will be returned, otherwise the method will attempt to parse the
     * file and to find the first class in it. PSR-compatible class means, it is named the same as the file and
     * the namespace corresponds to the file path relative to the vendor folder.
     *
     * @param string $absolutePath
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public static function findClassInFile(string $absolutePath, int $bufferSize = 512) : ?string
    {
        if (! file_exists($absolutePath)) {
            // $absolutePath = FilePathDataType::normalize($absolutePath, DIRECTORY_SEPARATOR);
            // $vendorPath = StringDataType::substringAfter($absolutePath, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR);
            throw new FileNotFoundError('Cannot get class from file "' . $absolutePath . '" - file not found!');
        }

        // First check class exists, where the namespace is simply the path in the vendor folder. That would be the
        // case for most app classes and will save us from actually reading the file here
        $pathNoralized = FilePathDataType::normalize($absolutePath, '\\');
        $pathInVendor = StringDataType::substringAfter($pathNoralized, '\\vendor\\');
        $psrClass = '\\' . StringDataType::substringBefore($pathInVendor, '.' . self::FILE_EXTENSION_PHP, $pathInVendor, false, true);
        if (class_exists($psrClass)) {
            return $psrClass;
        }

        // If our simple guess failed, look into the file and try to find the class name there
        $fp = fopen($absolutePath, 'r');
        $class = $namespace = $buffer = '';
        $i = 0;
        
        // In PHP8 the namespace is not a separate token type T_NAME_QUALIFIED, so we need to use
        // it if it is defined or fall back to T_STRING for prior PHP versions
        $T_NAME_QUALIFIED = (defined('T_NAME_QUALIFIED') ? constant('T_NAME_QUALIFIED') : T_STRING);
        
        while (! $class) {
            if (feof($fp)) {
                break;
            }
                
            $buffer .= fread($fp, $bufferSize);
            try {
                $tokens = @token_get_all($buffer);
            } catch (\ErrorException $e) {
                // Ignore errors of the tokenizer. Most of the errors will result from partial reading, when the read portion
                // of the code does not make sense to the tokenizer (e.g. unclosed comments, etc.)
            }
            $tokensCount = count($tokens);
            
            if (strpos($buffer, '{') === false) {
                continue;
            }
                
            for (; $i < count($tokens); $i ++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j = $i + 1; $j < count($tokens); $j ++) {
                        if ($tokens[$j][0] === $T_NAME_QUALIFIED) {
                            $namespace .= '\\' . $tokens[$j][1];
                        } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }
                
                if ($tokens[$i][0] === T_CLASS) {
                    for ($j = $i + 1; $j < $tokensCount; $j ++) {
                        if ($i+2 >= $tokensCount-1) {
                            return static::findClassInFile($absolutePath, $bufferSize*2);
                        }
                        $class = trim($tokens[$i + 2][1]);
                        break;
                    }
                }
            }
        }
        if (! $class) {
            return null;
        }
        return $namespace . '\\' . $class;
    }
    
    /**
     * Returns the PHP namespace of the file.
     * 
     * @param string $absolute_path
     * @param int $bufferSize
     * @throws \InvalidArgumentException
     * @return string|NULL
     */
    public static function findNamespaceOfFile(string $absolute_path) : ?string
    {
        if (! file_exists($absolute_path)) {
            throw new \InvalidArgumentException('Cannot get class from file "' . $absolute_path . '" - file not found!');
            return null;
        }
        if (is_dir($absolute_path)) {
            throw new \InvalidArgumentException('Cannot get class from file "' . $absolute_path . '" - it is a directory!');
            return null;
        }
        if (is_link($absolute_path)) {
            throw new \InvalidArgumentException('Cannot get class from file "' . $absolute_path . '" - it is a symlink!');
            return null;
        }
        $ns = NULL;
        $handle = fopen($absolute_path, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, 'namespace') === 0) {
                    $ns = rtrim(trim(substr($line, 9)), ';');
                    break;
                }
            }
            fclose($handle);
        }
        return $ns;
    }

    /**
     * Returns the path to the file containing the given class relative to the vendor folder
     * 
     * TODO add support for custom namespace paths by asking the autoloader as described
     * here https://stackoverflow.com/questions/48853306/how-to-get-the-file-path-where-a-class-would-be-loaded-from-while-using-a-compos
     * 
     * @param string $prototypeClass
     * @param string $extension
     * @return string
     */
    public static function findFileOfClass(string $prototypeClass, string $extension = '.php') : string
    {
        $path = str_replace('\\', '/', $prototypeClass);
        return ltrim($path, "/") . $extension;
    }

    /**
     * Returns the class name without the namespace

     * @param string $class
     * @return string
     */
    public static function stripNamespace(string $class) : string
    {
        $parts = explode('\\', $class);
        return array_pop($parts);
    }
}