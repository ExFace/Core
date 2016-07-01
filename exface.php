<?php namespace exface\Core;

use exface\Core\EventManager;
use exface\Core\Filemanager;
use exface\Core\utils;
use exface\Core\Factories\DataConnectorFactory;
use exface\Core\Factories\CmsConnectorFactory;
use exface\Core\Factories\AppFactory;
use exface\Core\NameResolver;
use exface\Core\ContextManager;
use exface\Core\DataManager;
use exface\Core\Factories\ModelLoaderFactory;
use exface\Core\Factories\EventFactory;
use exface\Core\Interfaces\Events\EventManagerInterface;

class exface {
	private $data;
	private $cms;
	private $mm;
	private $ui;
	private $db;
	private $context;
	private $running_apps = array();
	private $utils = null;
	private $event_manager = null;
	
	private $request_params = array();
	
	function __construct(){
		
		$vendor_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..';
		require_once 'splClassLoader.php';
		$classLoader = new \SplClassLoader(null, array($vendor_dir));
		$classLoader->register();
		
		require_once($vendor_dir.DIRECTORY_SEPARATOR.'autoload.php');
		
		$base_path = $vendor_dir.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR;
		
		// load the config
		require(dirname(__FILE__).'/config.php');;
		$this->config = $exf_config;
		$this->config['base_path'] = $base_path;
		
		$this->debug = false;
	}
	
	function start(){
		// start the event dispatcher
		$this->event_manager = new EventManager($this);
		$this->event_manager->dispatch(EventFactory::create_basic_event($this, 'Start'));
		// Initialize utilities
		$this->utils = new utils();
		
		// load the CMS connector
		$this->cms = CmsConnectorFactory::create($this->create_name_resolver($this->config['CMS_connector'], NameResolver::OBJECT_TYPE_CMS_CONNECTOR));
		// init data module
		$this->data = new DataManager($this);
		
		// load the metamodel manager
		$this->mm = new \exface\Core\Model\Model($this);
		
		// Init the ModelLoader
		$model_loader_name = NameResolver::create_from_string($this->config['model_loader'], NameResolver::OBJECT_TYPE_MODEL_LOADER, $this);
		$model_loader = ModelLoaderFactory::create($model_loader_name);
		$model_connection = DataConnectorFactory::create_from_alias($this, $this->config['model_data_connector'], $this->config['db']);
		$model_loader->set_data_connection($model_connection);
		$this->model()->set_model_loader($model_loader);
		
		// load the context
		$this->context = new ContextManager($this);
		
		// load the ui
		$this->ui = new \exface\Core\UiManager($this);
		
	}
	
	function get_config_value($param){
		return $this->config[$param];
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
	 * @return \exface\Core\AbstractApp
	 */
	public function get_app($app_alias){
		if (!$this->running_apps[$app_alias]){
			$this->running_apps[$app_alias] = AppFactory::create($this->create_name_resolver($app_alias, NameResolver::OBJECT_TYPE_APP));
		}
		return $this->running_apps[$app_alias];
	}
	
	/**
	 * Creates a default name resolver for an ExFace object specified by it's qualified alias and object type. The name
	 * resolver is a universal input container for factories in ExFace. Every factory hase a basic create() method that
	 * will create a new instance of whatever the name resolver describes.
	 * 
	 * @param string $qualified_alias A qualified alias may be
	 * - An ExFace alias with a proper namespace like exface.Core.SaveData for the SaveData action of the core app
	 * - A valid PHP class name
	 * - A path to the desired PHP class
	 * @param string $object_type One of the NameResolver::OBJECT_TYPE_xxx constants
	 * 
	 * @return \exface\Core\NameResolver
	 */
	public function create_name_resolver($qualified_alias, $object_type){
		return NameResolver::create_from_string($qualified_alias, $object_type, $this);
	}

	public function stop(){
		$this->context()->save_contexts();
		$this->data()->disconnect_all();
		$this->event_manager()->dispatch(EventFactory::create_basic_event($this, 'Stop'));
	}
	
	public function process_request(){
		// Determine the template
		$template_alias = $_REQUEST['exftpl'];
		unset($_REQUEST['exftpl']);
		// Read other request params
		$this->set_request_params($_REQUEST);
		
		// Process request
		if ($template_alias){
			$this->ui()->set_base_template_alias($template_alias);
			echo $this->ui()->get_template($template_alias)->process_request();
			$this->stop();
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
		return $this->request_params;		
	}
	
	public function get_request_param($param_name){
		$request = $this->get_request_params();
		return urldecode($request[$param_name]);
	}
	
	public function remove_request_param($param_name){
		unset($this->request_params[$param_name]);
	}
	
	private function set_request_params(array $params){
		$params = $this->cms()->remove_system_request_params($params);
		$this->request_params = $params;
		return $this;
	}
	
	public function set_request_param($param_name, $value){
		$this->request_params[$param_name] = $value;
		return $this;
	}
	
	/**
	 * Get the utilities class
	 * @return \exface\Core\utils
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
		return new \exface\Core\UxonObject();
	}
	
	/**
	 * Returns the absolute path of the exface installation folder
	 * @return string
	 */
	public function get_installation_path(){
		return dirname(__FILE__);
	}
	
	public function filemanager(){
		return new Filemanager();
	}

}
?>