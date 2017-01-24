<?php namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Exceptions\NameResolverError;

/**
 * The name resolver translates all kinds of references to important objects within ExFace to their class names, thus
 * allowing factories to instantiate widgets, apps, actions, etc. from different types of identifiers in a unified manner. 
 * Generally those identifiers can be
 * - qualified aliases in ExFace notation: e.g. exface.Core.SaveData for the SaveData core action
 * - valid PHP class names with respective namespaces
 * - file paths to the required class relative to the installation folder
 * The name resolver takes care of translating all those possibilities to class names, that can be instantiated by
 * factories. The reason why all those different possiblities exist, is that the users, that build the UI do not need
 * to know anything about the internally required classes. Moreover app developers are free to create their own name
 * resolvers with proprietary class location logic, still keeping the simplified syntax used by users.
 * 
 * This default name resolver is always available through exface()->create_name_resolver() or by calling
 * NameResolver::create_from_string(). Custom name resolvers must be instantiated directly (e.g. via overriding the
 * create_from_string() method) and passed to factories manually. Factory methods, that do not require a name resolver
 * will use the default one.
 * 
 * @author Andrej Kabachnik
 *
 */
class NameResolver extends AbstractExfaceClass implements NameResolverInterface {
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
	const CLASS_NAMESPACE_SEPARATOR = '\\';
	const NAMESPACE_SEPARATOR = '.';
	const NORMALIZED_DIRECTORY_SEPARATOR = '/';
	const APPS_NAMESPACE = '\\';
	const APPS_DIRECTORY = 'Apps';
	
	private $object_type = null;
	private $namespace = null;
	private $alias = null;
	
	/**
	 * Returns the namespace part of a given string (e.g. "exface.Core" for "exface.Core.OBJECT") 
	 * NOTE: This is the ExFace-namespace. To get the PHP-namespace use get_class_namespace() instead.
	 * @param string $string
	 * @param exface $exface
	 * @return string
	 */
	protected static function get_namespace_from_string($string, $separator = self::NAMESPACE_SEPARATOR, $object_type = null){
		$result = '';
		$pos = strripos($string, $separator);
		if ($pos !== false){
			$result = str_replace($separator, self::NAMESPACE_SEPARATOR, substr($string, 0, $pos));
		} 
		
		// Some object types have their own folders, that are not present in the internal namespace. We need to strip
		// those folders
		switch ($object_type){
			case self::OBJECT_TYPE_ACTION: $result = str_replace(self::NAMESPACE_SEPARATOR . self::OBJECT_TYPE_ACTION, '', $result); break;
		}
		
		return $result;
	}
	
	/**
	 * Returns the alias part of a given string (e.g. "OBJECT" for "exface.Core.OBJECT")
	 * @param string $string
	 * @param exface $exface
	 * @return string
	 */
	protected static function get_alias_from_string($string, $separator = self::NAMESPACE_SEPARATOR){		
		$pos = strripos($string, $separator);
		if ($pos !== false){
			return str_replace($separator, self::NAMESPACE_SEPARATOR, substr($string, ($pos+1)));
		} else {
			return $string;
		}
	}
	
	public static function create_from_string($string, $object_type, Workbench $exface){
		$instance = new self($exface);
		$instance->set_object_type($object_type);
		if ((mb_strpos($string, DIRECTORY_SEPARATOR) > 0 || mb_strpos($string, self::NORMALIZED_DIRECTORY_SEPARATOR) !== false) 
		&& mb_strpos($string, '.php') !== false){
			// If the string contains "/" or "\" (but the first character is not "\") and also contains ".php" - treat it as a file name
			// In this case, we need to normalize it by replacing all "/" by the DIRECTORY_SEPARATOR of the current system, so all other
			// code knows, it's a valid path.
			$string = str_replace(array('.php', self::APPS_DIRECTORY . DIRECTORY_SEPARATOR), '', $string);
			$string = str_replace(self::NORMALIZED_DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $string);
			$instance->set_alias(self::get_alias_from_string($string, DIRECTORY_SEPARATOR));
			$instance->set_namespace(self::get_namespace_from_string($string, DIRECTORY_SEPARATOR, $object_type));
		} elseif (mb_strpos($string, self::CLASS_NAMESPACE_SEPARATOR) === 0){
			// If the first character of the string is "\" - it is a class name with a namespace
			// TODO
		} else {
			// Otherwise treat the string as an alias
			$instance->set_alias(self::get_alias_from_string($string));
			$instance->set_namespace(self::get_namespace_from_string($string));
		}
		return $instance;
	}
	
	public function get_object_type() {
		return $this->object_type;
	}
	
	public function set_object_type($value) {
		$this->object_type = $value;
		return $this;
	}
	
	public function get_alias() {
		return $this->alias;
	}
	
	public function get_alias_with_namespace(){
		return $this->get_namespace() . self::NAMESPACE_SEPARATOR . $this->get_alias();
	}
	
	public function set_alias($value) {
		$this->alias = $value;
		return $this;
	}
	
	public function get_namespace() {
		return $this->namespace;
	}
	
	public function set_namespace($value) {
		$this->namespace = $value;
		return $this;
	}
	
	public function get_vendor(){
		$pos = stripos($this->get_namespace(), NameResolver::NAMESPACE_SEPARATOR);
		if ($pos !== false){
			return substr($this->get_namespace(), 0, $pos);
		} else {
			return $this->get_namespace();
		}
	}
	
	/**
	 * Returns the resolved class name in PSR-1 notation
	 * @return string
	 */
	public function get_class_name_with_namespace(){
		$result = $this->get_class_namespace();
		switch ($this->get_object_type()){
			case self::OBJECT_TYPE_APP:
				$result .= self::CLASS_NAMESPACE_SEPARATOR . $this->get_alias() . 'App';
				break;
			case self::OBJECT_TYPE_TEMPLATE:
				$result .= self::CLASS_NAMESPACE_SEPARATOR . $this->get_alias() 
						. self::CLASS_NAMESPACE_SEPARATOR . 'Template' 
						. self::CLASS_NAMESPACE_SEPARATOR . $this->get_alias();
				break;
			default: 
				$result .= self::CLASS_NAMESPACE_SEPARATOR . $this->get_alias();
		}
		return $result;
	}
	
	public function get_class_namespace(){
		switch ($this->get_object_type()){
			case self::OBJECT_TYPE_FORMULA:
			case self::OBJECT_TYPE_ACTION:	
				$result = self::APPS_NAMESPACE;
				if ($this->get_namespace()){
					$result .= self::convert_namespace_to_class_namespace($this->get_namespace());
				} else {
					$result .= 'exface\\Core';
				}
				$result .= self::CLASS_NAMESPACE_SEPARATOR . self::get_subdir_from_object_type($this->get_object_type());
				break;
			case self::OBJECT_TYPE_APP:
				$result = self::APPS_NAMESPACE . self::convert_namespace_to_class_namespace($this->get_alias_with_namespace());
				break;
			default: 
				$result = self::APPS_NAMESPACE . self::convert_namespace_to_class_namespace($this->get_namespace());
		}
		return $result;
	}
	
	protected static function convert_namespace_to_class_namespace($string){
		return str_replace(self::NAMESPACE_SEPARATOR, self::CLASS_NAMESPACE_SEPARATOR, $string);
	}
	
	protected static function convert_namespace_filesystem_path($string){
		return str_replace(self::NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $string);
	}
	
	protected static function convert_class_namespace_filesystem_path($string){
		return str_replace(self::CLASS_NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $string);
	}
	
	protected static function get_subdir_from_object_type($object_type_string){
		switch ($object_type_string){
			case self::OBJECT_TYPE_APP: return '';
			default: return $object_type_string;
		}
	}
	
	public function get_class_directory(){
		return self::convert_class_namespace_filesystem_path($this->get_class_namespace());
	}
	
	public function class_exists(){
		return class_exists($this->get_class_name_with_namespace());
	}
	
	public function get_factory_class_name(){
		$result = '';
		$factory_namespace = $this->get_default_factory_class_namespace();
		switch (self::get_object_type()){
			case self::OBJECT_TYPE_ACTION: $result = $factory_namespace . 'ActionFactory'; break;
			case self::OBJECT_TYPE_APP: $result = $factory_namespace . 'AppFactory'; break;
			case self::OBJECT_TYPE_CMS_CONNECTOR: $result = $factory_namespace . 'CmsConnectorFactory'; break;
			case self::OBJECT_TYPE_DATA_CONNECTOR: $result = $factory_namespace . 'DataConnectorFactory'; break;
			case self::OBJECT_TYPE_FORMULA: $result = $factory_namespace . 'FormulaFactory'; break;
			case self::OBJECT_TYPE_QUERY_BUILDER: $result = $factory_namespace . 'QueryBuilderFactory'; break;
			case self::OBJECT_TYPE_WIDGET: $result = $factory_namespace . 'WidgetFactory'; break;
		}
		return $result;
	}
	
	public static function get_default_factory_class_namespace(){
		return self::CLASS_NAMESPACE_SEPARATOR . 'exface' . self::CLASS_NAMESPACE_SEPARATOR . 'Core' . self::CLASS_NAMESPACE_SEPARATOR . 'Factories' . self::CLASS_NAMESPACE_SEPARATOR;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\NameResolverInterface::validate()
	 */
	public function validate(){
		if (!$this->class_exists()){
			throw new NameResolverError('Cannot locate ' . $this->get_object_type() . ' "' . $this->get_alias_with_namespace() . '" : class "' . $this->get_class_name_with_namespace() . '" not found!');
		}
		return $this;
	}	
}