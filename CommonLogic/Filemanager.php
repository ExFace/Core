<?php namespace exface\Core\CommonLogic;

use Symfony\Component\Filesystem\Filesystem;
use exface\Core\Interfaces\ExfaceClassInterface;

class Filemanager extends Filesystem implements ExfaceClassInterface {	
	private $exface = null;
	private $path_to_cache_folder = null;
	private $path_to_config_folder = null;
	private $path_to_user_data_folder = null;
	
	public function __construct(Workbench &$exface){
		$this->exface = $exface;
	}
	
	/**
	 * Returns the absolute path to the base installation folder (e.g. c:\xampp\htdocs\exface\exface)
	 * @return string
	 */
	public function get_path_to_base_folder(){
		return $this->get_workbench()->get_installation_path();
	}
	
	/**
	 * Returns the absolute path to the base installation folder (e.g. c:\xampp\htdocs\exface\exface\vendor)
	 * @return string
	 */
	public function get_path_to_vendor_folder(){
		return $this->get_path_to_base_folder() . DIRECTORY_SEPARATOR . 'vendor';
	}
	
	/**
	 * Returns the absolute path to the base installation folder (e.g. c:\xampp\htdocs\exface\exface\UserData)
	 * @return string
	 */
	public function get_path_to_user_data_folder(){
		if (is_null($this->path_to_user_data_folder)){
			$this->path_to_user_data_folder = $this->get_path_to_base_folder() . DIRECTORY_SEPARATOR . EXF_FOLDER_USER_DATA;
			if (!is_dir($this->path_to_user_data_folder)){
				mkdir($this->path_to_user_data_folder);
			}
		}
		return $this->path_to_user_data_folder;
	}
	
	/**
	 * Returns the absolute path to the main cache folder (e.g. c:\xampp\htdocs\exface\exface\cache)
	 * @return string
	 */
	public function get_path_to_cache_folder(){
		if (is_null($this->path_to_cache_folder)){
			$this->path_to_cache_folder = $this->get_path_to_base_folder() . DIRECTORY_SEPARATOR . 'cache';
			if (!is_dir($this->path_to_cache_folder)){
				mkdir($this->path_to_cache_folder);
			}
		}
		return $this->path_to_cache_folder;
	}
	
	/**
	 * Returns the absolute path to main config folder (e.g. c:\xampp\htdocs\exface\exface\config)
	 * @return string
	 */
	public function get_path_to_config_folder(){
		if (is_null($this->path_to_config_folder)){
			$this->path_to_config_folder = $this->get_path_to_base_folder() . DIRECTORY_SEPARATOR . 'config';
			if (!is_dir($this->path_to_config_folder)){
				mkdir($this->path_to_config_folder);
			}
		}
		return $this->path_to_config_folder;
	}
	
	/**
	 * Copies a complete folder to a new location including all subfolders
	 * @param string $originDir
	 * @param string $destinationDir
	 */
	public function copyDir($originDir, $destinationDir, $override = false) {
		$dir = opendir($originDir);
		@mkdir($destinationDir);
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($originDir . DIRECTORY_SEPARATOR . $file) ) {
					$this->copyDir($originDir . DIRECTORY_SEPARATOR . $file,$destinationDir . DIRECTORY_SEPARATOR . $file);
				}
				else {
					$this->copy($originDir . DIRECTORY_SEPARATOR . $file, $destinationDir . DIRECTORY_SEPARATOR . $file, $override);
				}
			}
		}
		closedir($dir);
	}
	
	public function get_workbench(){
		return $this->exface;
	}
}
?>