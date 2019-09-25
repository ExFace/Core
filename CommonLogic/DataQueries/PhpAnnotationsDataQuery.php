<?php
namespace exface\Core\CommonLogic\DataQueries;

use Wingu\OctopusCore\Reflection\ReflectionClass;

class PhpAnnotationsDataQuery extends FileContentsDataQuery
{

    private $class_name_with_namespace = null;

    private $reflection_class = null;

    public function getClassNameWithNamespace()
    {
        if (is_null($this->class_name_with_namespace)) {
            return $this::getClassFromFile($this->getPathAbsolute());
        }
        
        return $this->class_name_with_namespace;
    }

    public function setClassNameWithNamespace($value)
    {
        $this->class_name_with_namespace = $value;
        return $this;
    }

    /**
     *
     * @return \Wingu\OctopusCore\Reflection\ReflectionClass
     */
    public function getReflectionClass()
    {
        return $this->reflection_class;
    }

    /**
     *
     * @param ReflectionClass $value            
     * @return \exface\Core\CommonLogic\DataQueries\PhpAnnotationsDataQuery
     */
    public function setReflectionClass(ReflectionClass $value)
    {
        $this->reflection_class = $value;
        return $this;
    }

    /**
     * Returns the qualified class name of the first class defined in the file.
     *
     * @param string $absolute_path            
     * @throws \InvalidArgumentException
     * @return string
     */
    protected static function getClassFromFile($absolute_path, int $bufferSize = 512)
    {
        if (! file_exists($absolute_path) && ! is_dir($absolute_path)) {
            throw new \InvalidArgumentException('Cannot get class from file "' . $absolute_path . '" - file not found!');
            return null;
        }
        $fp = fopen($absolute_path, 'r');
        $class = $namespace = $buffer = '';
        $i = 0;
        while (! $class) {
            if (feof($fp))
                break;
            
            $buffer .= fread($fp, $bufferSize);
            try {
                $tokens = @token_get_all($buffer);
            } catch (\ErrorException $e) {
                // Ignore errors of the tokenizer. Most of the errors will result from partial reading, when the read portion
                // of the code does not make sense to the tokenizer (e.g. unclosed comments, etc.)
            }
            $tokensCount = count($tokens);
            
            if (strpos($buffer, '{') === false)
                continue;
            
            for (; $i < count($tokens); $i ++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j = $i + 1; $j < count($tokens); $j ++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $namespace .= '\\' . $tokens[$j][1];
                        } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }
                
                if ($tokens[$i][0] === T_CLASS) {
                    for ($j = $i + 1; $j < $tokensCount; $j ++) {
                        if ($i+2 >= $tokensCount-1) {
                            return static::getClassFromFile($absolute_path, $bufferSize*2);
                        }
                        $class = trim($tokens[$i + 2][1]);
                        break;
                    }
                }
            }
        }
        return $namespace . '\\' . $class;
    }
}
?>