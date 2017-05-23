<?php namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\EventManager;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Log\Log;
use exface\Core\utils;
use exface\Core\Factories\DataConnectorFactory;
use exface\Core\Factories\CmsConnectorFactory;
use exface\Core\Factories\AppFactory;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\CommonLogic\ContextManager;
use exface\Core\CommonLogic\DataManager;
use exface\Core\Factories\ModelLoaderFactory;
use exface\Core\Factories\EventFactory;
use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\DebuggerInterface;
use exface\Core\CoreApp;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\NameResolverInterface;

class Workbench {
	private $started = false;
	private $data;
	private $cms;
	private $mm;
	private $ui;
	private $db;
	private $debugger;
	private $logger;
	private $context;
	private $running_apps = array();
	private $utils = null;
	private $event_manager = null;
	private $vendor_dir_path = '';
	private $installation_path = null;
	
	private $request_params = null;
	
	public function __construct(){
		if (substr(phpversion(), 0, 1) == 5){
			require_once 'Php5Compatibility.php';
		}
		
		// Determine the absolute path to the vendor folder
		$this->vendor_dir_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..';
		
		// Init the class loader
		require_once 'splClassLoader.php';
		$classLoader = new \SplClassLoader(null, array($this->vendor_dir_path));
		$classLoader->register();
		
		// Init composer autoload
		require_once($this->vendor_dir_path.DIRECTORY_SEPARATOR.'autoload.php');
		
		// Load the internal constants file
		require_once('Constants.php');
	}
	
	public function __destruct(){
		$this->stop();
	}
	
	public function start(){
		// logger
		$this->logger = Log::getErrorLogger($this);

		// Start the error handler
		$dbg = new Debugger($this->logger);
		$this->set_debugger($dbg);
		if ($this->get_config()->get_option('DEBUG.PRETTIFY_ERRORS')){
			$dbg->set_prettify_errors(true);
		}
		
		// start the event dispatcher
		$this->event_manager = new EventManager($this);
		$this->event_manager->dispatch(EventFactory::create_basic_event($this, 'Start'));
		// Initialize utilities
		$this->utils = new utils();
		
		// load the CMS connector
		$this->cms = CmsConnectorFactory::create($this->create_name_resolver($this->get_config()->get_option('CMS_CONNECTOR'), NameResolver::OBJECT_TYPE_CMS_CONNECTOR));
		// init data module
		$this->data = new DataManager($this);
		
		// load the metamodel manager
		$this->mm = new \exface\Core\CommonLogic\Model\Model($this);
		
		// Init the ModelLoader
		$model_loader_name = NameResolver::create_from_string($this->get_config()->get_option('MODEL_LOADER'), NameResolver::OBJECT_TYPE_MODEL_LOADER, $this);
		if (!$model_loader_name->class_exists()){
			throw new InvalidArgumentException('No valid model loader found in current configuration - please add a valid "MODEL_LOADER" : "file_path_or_qualified_alias_or_qualified_class_name" to your config in "' . $this->filemanager()->get_path_to_config_folder() . '"');
		}
		$model_loader = ModelLoaderFactory::create($model_loader_name);
		$model_connection = DataConnectorFactory::create_from_alias($this, $this->get_config()->get_option('MODEL_DATA_CONNECTOR'));
		$model_loader->set_data_connection($model_connection);
		$this->model()->set_model_loader($model_loader);
		
		// load the context
		$this->context = new ContextManager($this);
		
		// load the ui
		$this->ui = new \exface\Core\CommonLogic\UiManager($this);

		$this->started = true;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Workbench
	 */
	public static function start_new_instance(){
		$instance = new self();
		$instance->start();
		return $instance;
	}
	
	/**
	 * Returns TRUE if start() was successfully called on this workbench instance and FALSE otherwise.
	 * 
	 * @return boolean
	 */
	public function is_started(){
		return $this->started;
	}
	
	/**
	 * @return ConfigurationInterface
	 */
	public function get_config(){
		return $this->get_app('exface.Core')->get_config();
	}
	
	/**
	 * Returns a unique ID of the request, that is being handled by this instance. Can be used for cache invalidation of persistant caches
	 * TODO Move to the request context scope completely
	 */
	public function get_request_id() {
		return $this->context()->get_scope_request()->get_requets_id();
	}
	
	/**
	 * TODO Move to the request context scope completely
	 * @param unknown $value
	 */
	public function set_request_id($value){
		$this->context()->get_scope_request()->set_requets_id($value);
		return $this;
	}
	
	public function model(){
		return $this->mm;
	}
	
	/**
	 * @return ContextManager
	 */
	public function context(){
		return $this->context;
	}
	
	/**
	 * @return CMSInterface
	 */
	public function CMS(){
		return $this->cms;
	}
	
	/**
	 * @return DataManager
	 */
	public function data(){
		return $this->data;
	}
	
	public function ui(){
		return $this->ui;
	}
	
	/**
	 * Launches an ExFace app and returns it. Apps are cached and kept running for script (request) window
	 * @param string $app_alias
	 * @return AppInterface
	 */
	public function get_app($app_alias){
		if (!array_key_exists($app_alias, $this->running_apps)){
			$this->running_apps[$app_alias] = AppFactory::create($this->create_name_resolver($app_alias, NameResolver::OBJECT_TYPE_APP));
		}
		return $this->running_apps[$app_alias];
	}
	
	/**
	 * Returns the core app
	 * @return CoreApp
	 */
	public function get_core_app(){
		return $this->get_app('exface.Core');
	}
	
	/**
	 * Creates a default name resolver for an ExFace object specified by it's qualified alias and object type. The name
	 * resolver is a universal input container for factories in ExFace. Every factory hase a basic create() method that
	 * will create a new instance of whatever the name resolver describes.
	 * 
	 * @param string $qualified_alias A qualified alias may be
	 * - An ExFace alias with a proper namespace like Workbench.Core.SaveData for the SaveData action of the core app
	 * - A valid PHP class name
	 * - A path to the desired PHP class
	 * @param string $object_type One of the NameResolver::OBJECT_TYPE_xxx constants
	 * 
	 * @return NameResolverInterface
	 */
	public function create_name_resolver($qualified_alias, $object_type){
		return NameResolver::create_from_string($qualified_alias, $object_type, $this);
	}

	public function stop(){
		if ($this->is_started()){
			$this->context()->save_contexts();
			$this->data()->disconnect_all();
			$this->event_manager()->dispatch(EventFactory::create_basic_event($this, 'Stop'));
		}
	}
	
	public function process_request(){
		// Determine the template
		$template_alias = $this->get_request_param('exftpl');
		$this->remove_request_param('exftpl');
		
		// Process request
		if ($template_alias){
			$this->ui()->set_base_template_alias($template_alias);
			echo $this->ui()->get_template($template_alias)->process_request();
		} else {
			// If template alias not given - it's not an AJAX request, so do not do anything here, wait for the CMS to call request processing
			// The reason for this is, that the CMS will select the template.
			// IDEA this a bit a strange approach. Perhaps, the CMS should also call this method but give the desired template as a parameter
		}
		return;
	}
	
	/**
	 * Returns the parameters of the current request (URL params for GET-requests, data of POST-requests, etc.)
	 * @return array
	 */
	public function get_request_params(){
		if (is_null($this->request_params)){
			$this->request_params = $this->cms()->remove_system_request_params($_REQUEST);
		}
		return $this->request_params;		
	}
	
	public function get_request_param($param_name){
		$request = $this->get_request_params();
		return urldecode($request[$param_name]);
	}
	
	public function remove_request_param($param_name){
		unset($this->request_params[$param_name]);
	}
	
	public function set_request_param($param_name, $value){
		$this->request_params[$param_name] = $value;
		return $this;
	}
	
	/**
	 * Get the utilities class
	 * @return \Workbench\Core\utils
	 */
	public function utils(){
		return $this->utils;
	}
	
	/**
	 * Returns the central event manager (dispatcher)
	 * @return EventManagerInterface
	 */
	public function event_manager(){
		return $this->event_manager;
	}
	
	public function create_uxon_object(){
		return new \exface\Core\CommonLogic\UxonObject();
	}
	
	/**
	 * Returns the absolute path of the ExFace installation folder
	 * @return string
	 */
	public function get_installation_path(){
		if (is_null($this->installation_path)){
			$this->installation_path = Filemanager::path_normalize($this->vendor_dir_path . DIRECTORY_SEPARATOR . '..', DIRECTORY_SEPARATOR);
		}
		return $this->installation_path;
	}
	
	/**
	 * 
	 * @return Filemanager
	 */
	public function filemanager(){
		return new Filemanager($this);
	}
	
	/**
	 * 
	 * @return \exface\Core\Interfaces\DebuggerInterface
	 */
	public function get_debugger() {
		return $this->debugger;
	}
	
	public function set_debugger(DebuggerInterface $value) {
		$this->debugger = $value;
		return $this;
	} 
	
	/**
	 * 
	 * @return \exface\Core\Interfaces\Log\LoggerInterface
	 */
	public function get_logger(){
		return $this->logger;
	}

}
?>