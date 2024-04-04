<?php
namespace exface\Core\DataTypes;

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
     * Returns the qualified class name of the first class defined in the file.
     *
     * @param string $absolute_path
     * @throws \InvalidArgumentException
     * @return string|null
     */
    public static function findClassInFile(string $absolute_path, int $bufferSize = 512) : ?string
    {
        if (! file_exists($absolute_path) && ! is_dir($absolute_path)) {
            throw new \InvalidArgumentException('Cannot get class from file "' . $absolute_path . '" - file not found!');
            return null;
        }
        
        $fp = fopen($absolute_path, 'r');
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
                            return static::findClassInFile($absolute_path, $bufferSize*2);
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
        if (! file_exists($absolute_path) && ! is_dir($absolute_path)) {
            throw new \InvalidArgumentException('Cannot get class from file "' . $absolute_path . '" - file not found!');
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
}