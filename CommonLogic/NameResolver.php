<?php
namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Exceptions\NameResolverError;

/**
 * The name resolver translates all kinds of references to important objects within ExFace to their class names, thus
 * allowing factories to instantiate widgets, apps, actions, etc.
 * from different types of identifiers in a unified manner.
 * Generally those identifiers can be
 * - qualified aliases in ExFace notation: e.g. exface.Core.SaveData for the SaveData core action
 * - valid PHP class names with respective namespaces
 * - file paths to the required class relative to the installation folder
 * The name resolver takes care of translating all those possibilities to class names, that can be instantiated by
 * factories. The reason why all those different possiblities exist, is that the users, that build the UI do not need
 * to know anything about the internally required classes. Moreover app developers are free to create their own name
 * resolvers with proprietary class location logic, still keeping the simplified syntax used by users.
 *
 * This default name resolver is always available through exface()->createNameResolver() or by calling
 * NameResolver::createFromString(). Custom name resolvers must be instantiated directly (e.g. via overriding the
 * create_from_string() method) and passed to factories manually. Factory methods, that do not require a name resolver
 * will use the default one.
 *
 * @author Andrej Kabachnik
 *        
 */
class NameResolver extends AbstractExfaceClass implements NameResolverInterface
{

    const OBJECT_TYPE_FORMULA = 'Formulas';

    const OBJECT_TYPE_DATA_CONNECTOR = 'DataConnectors';

    const OBJECT_TYPE_QUERY_BUILDER = 'QueryBuilders';

    const OBJECT_TYPE_CMS_CONNECTOR = 'CmsConnectors';

    const OBJECT_TYPE_APP = 'Apps';

    const OBJECT_TYPE_ACTION = 'Actions';

    const OBJECT_TYPE_WIDGET = 'Widgets';

    const OBJECT_TYPE_MODEL_LOADER = 'ModelLoaders';

    const OBJECT_TYPE_BEHAVIOR = 'Behaviors';

    const OBJECT_TYPE_TEMPLATE = 'Template';
    
    const OBJECT_TYPE_CONTEXT = 'Contexts';

    const CLASS_NAMESPACE_SEPARATOR = '\\';

    const NAMESPACE_SEPARATOR = '.';

    const NORMALIZED_DIRECTORY_SEPARATOR = '/';

    const APPS_NAMESPACE = '\\';

    const APPS_DIRECTORY = 'Apps';

    private $object_type = null;

    private $namespace = null;

    private $alias = null;

    /**
     * Returns the namespace part of a given string (e.g.
     * "exface.Core" for "exface.Core.OBJECT")
     * NOTE: This is the ExFace-namespace. To get the PHP-namespace use get_class_namespace() instead.
     *
     * @param string $string            
     * @param Workbench $exface            
     * @return string
     */
    protected static function getNamespaceFromString($string, $separator = self::NAMESPACE_SEPARATOR, $object_type = null)
    {
        $result = '';
        $pos = strripos($string, $separator);
        if ($pos !== false) {
            $result = str_replace($separator, self::NAMESPACE_SEPARATOR, substr($string, 0, $pos));
        }
        
        // Some object types have their own folders, that are not present in the internal namespace. We need to strip
        // those folders
        switch ($object_type) {
            case self::OBJECT_TYPE_ACTION:
                $result = str_replace(self::NAMESPACE_SEPARATOR . self::OBJECT_TYPE_ACTION, '', $result);
                break;
        }
        
        return $result;
    }

    /**
     * Returns the alias part of a given string (e.g.
     * "OBJECT" for "exface.Core.OBJECT")
     *
     * @param string $string            
     * @param Workbench $exface            
     * @return string
     */
    protected static function getAliasFromString($string, $separator = self::NAMESPACE_SEPARATOR)
    {
        $pos = strripos($string, $separator);
        if ($pos !== false) {
            return str_replace($separator, self::NAMESPACE_SEPARATOR, substr($string, ($pos + 1)));
        } else {
            return $string;
        }
    }

    public static function createFromString($string, $object_type, Workbench $exface)
    {
        $instance = new self($exface);
        $instance->setObjectType($object_type);
        if ((mb_strpos($string, DIRECTORY_SEPARATOR) > 0 || mb_strpos($string, self::NORMALIZED_DIRECTORY_SEPARATOR) !== false) && mb_strpos($string, '.php') !== false) {
            // If the string contains "/" or "\" (but the first character is not "\") and also contains ".php" - treat it as a file name
            // In this case, we need to normalize it by replacing all "/" by the DIRECTORY_SEPARATOR of the current system, so all other
            // code knows, it's a valid path.
            $string = str_replace(array(
                '.php',
                self::APPS_DIRECTORY . DIRECTORY_SEPARATOR
            ), '', $string);
            $string = str_replace(self::NORMALIZED_DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $string);
            $instance->setAlias(self::getAliasFromString($string, DIRECTORY_SEPARATOR));
            $instance->setNamespace(self::getNamespaceFromString($string, DIRECTORY_SEPARATOR, $object_type));
        } elseif (mb_strpos($string, self::CLASS_NAMESPACE_SEPARATOR) === 0) {
            // If the first character of the string is "\" - it is a class name with a namespace
            // TODO
        } else {
            // Otherwise treat the string as an alias
            $instance->setAlias(self::getAliasFromString($string));
            $instance->setNamespace(self::getNamespaceFromString($string));
        }
        return $instance;
    }

    public function getObjectType()
    {
        return $this->object_type;
    }

    public function setObjectType($value)
    {
        $this->object_type = $value;
        return $this;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . self::NAMESPACE_SEPARATOR . $this->getAlias();
    }

    public function setAlias($value)
    {
        $this->alias = $value;
        return $this;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function getNamespaceDirectory()
    {
        return static::convertNamespaceFilesystemPath($this->getNamespace());
    }

    public function setNamespace($value)
    {
        $this->namespace = $value;
        return $this;
    }

    public function getVendor()
    {
        $pos = stripos($this->getNamespace(), NameResolver::NAMESPACE_SEPARATOR);
        if ($pos !== false) {
            return substr($this->getNamespace(), 0, $pos);
        } else {
            return $this->getNamespace();
        }
    }

    /**
     * Returns the resolved class name in PSR-1 notation
     *
     * @return string
     */
    public function getClassNameWithNamespace()
    {
        $result = $this->getClassNamespace();
        switch ($this->getObjectType()) {
            case self::OBJECT_TYPE_APP:
                $result .= self::CLASS_NAMESPACE_SEPARATOR . $this->getAlias() . 'App';
                break;
            case self::OBJECT_TYPE_TEMPLATE:
                $result .= self::CLASS_NAMESPACE_SEPARATOR . $this->getAlias() . self::CLASS_NAMESPACE_SEPARATOR . 'Template' . self::CLASS_NAMESPACE_SEPARATOR . $this->getAlias();
                break;
            default:
                $result .= self::CLASS_NAMESPACE_SEPARATOR . $this->getAlias();
        }
        return $result;
    }

    public function getClassNamespace()
    {
        switch ($this->getObjectType()) {
            case self::OBJECT_TYPE_CONTEXT:
            case self::OBJECT_TYPE_FORMULA:
            case self::OBJECT_TYPE_ACTION:
                $result = self::APPS_NAMESPACE;
                if ($this->getNamespace()) {
                    $result .= self::convertNamespaceToClassNamespace($this->getNamespace());
                } else {
                    $result .= 'exface\\Core';
                }
                $result .= self::CLASS_NAMESPACE_SEPARATOR . self::getSubdirFromObjectType($this->getObjectType());
                break;
            case self::OBJECT_TYPE_APP:
                $result = self::APPS_NAMESPACE . self::convertNamespaceToClassNamespace($this->getAliasWithNamespace());
                break;
            default:
                $result = self::APPS_NAMESPACE . self::convertNamespaceToClassNamespace($this->getNamespace());
        }
        return $result;
    }

    protected static function convertNamespaceToClassNamespace($string)
    {
        return str_replace(self::NAMESPACE_SEPARATOR, self::CLASS_NAMESPACE_SEPARATOR, $string);
    }

    protected static function convertNamespaceFilesystemPath($string)
    {
        return str_replace(self::NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $string);
    }

    protected static function convertClassNamespaceFilesystemPath($string)
    {
        return str_replace(self::CLASS_NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $string);
    }

    protected static function getSubdirFromObjectType($object_type_string)
    {
        switch ($object_type_string) {
            case self::OBJECT_TYPE_APP:
                return '';
            default:
                return $object_type_string;
        }
    }

    public function getClassDirectory()
    {
        return self::convertClassNamespaceFilesystemPath($this->getClassNamespace());
    }

    public function classExists()
    {
        return class_exists($this->getClassNameWithNamespace());
    }

    public function getFactoryClassName()
    {
        $result = '';
        $factory_namespace = $this->getDefaultFactoryClassNamespace();
        switch (self::getObjectType()) {
            case self::OBJECT_TYPE_ACTION:
                $result = $factory_namespace . 'ActionFactory';
                break;
            case self::OBJECT_TYPE_APP:
                $result = $factory_namespace . 'AppFactory';
                break;
            case self::OBJECT_TYPE_CMS_CONNECTOR:
                $result = $factory_namespace . 'CmsConnectorFactory';
                break;
            case self::OBJECT_TYPE_DATA_CONNECTOR:
                $result = $factory_namespace . 'DataConnectorFactory';
                break;
            case self::OBJECT_TYPE_FORMULA:
                $result = $factory_namespace . 'FormulaFactory';
                break;
            case self::OBJECT_TYPE_QUERY_BUILDER:
                $result = $factory_namespace . 'QueryBuilderFactory';
                break;
            case self::OBJECT_TYPE_WIDGET:
                $result = $factory_namespace . 'WidgetFactory';
                break;
        }
        return $result;
    }

    public static function getDefaultFactoryClassNamespace()
    {
        return self::CLASS_NAMESPACE_SEPARATOR . 'exface' . self::CLASS_NAMESPACE_SEPARATOR . 'Core' . self::CLASS_NAMESPACE_SEPARATOR . 'Factories' . self::CLASS_NAMESPACE_SEPARATOR;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\NameResolverInterface::validate()
     */
    public function validate()
    {
        if (! $this->classExists()) {
            throw new NameResolverError('Cannot locate ' . $this->getObjectType() . ' "' . $this->getAliasWithNamespace() . '" : class "' . $this->getClassNameWithNamespace() . '" not found!');
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\NameResolverInterface::getAppAlias()
     */
    public function getAppAlias(){
        // TODO once subnamecpaces inside apps become possible, we will need
        // to strip the off here somehow.
        return $this->getNamespace();
    }
}