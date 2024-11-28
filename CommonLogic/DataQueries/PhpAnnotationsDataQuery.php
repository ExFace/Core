<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\Exceptions\FileNotFoundError;
use Wingu\OctopusCore\Reflection\ReflectionClass;
use exface\Core\DataTypes\PhpFilePathDataType;

class PhpAnnotationsDataQuery extends FileContentsDataQuery
{

    private $class_name_with_namespace = null;

    private $reflection_class = null;

    public function getClassNameWithNamespace()
    {
        if (null === $this->class_name_with_namespace) {
            try {
                return PhpFilePathDataType::findClassInFile($this->getPathAbsolute());
            } catch (FileNotFoundError $e) {
                return null;
            }
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
}
?>