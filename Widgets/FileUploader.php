<?php
namespace exface\Core\Widgets;
class FileUploader extends AbstractWidget {
	private $default_file_description = 'Upload';
	/**
	 * @uxon allowed_extensions Comma separated list of allowed file extensions (case insensitive) - all by default
	 * @var string
	 */
	private $allowed_extensions = '';
	
	/**
	 * @var max_file_size_bytes Maximum upload size in bytes - 10 000 000 by default
	 * @var integer
	 */
	private $max_file_size_bytes = 10000000;
	
	public function get_default_file_description() {
		return $this->default_file_description;
	}
	
	public function set_default_file_description($value) {
		$this->default_file_description = $value;
		return $this;
	} 
	
	public function get_allowed_extensions() {
		return $this->allowed_extensions;
	}
	
	public function set_allowed_extensions($value) {
		$this->allowed_extensions = $value;
		return $this;
	}
	
	public function get_max_file_size_bytes() {
		return $this->max_file_size_bytes;
	}
	
	public function set_max_file_size_bytes($value) {
		$this->max_file_size_bytes = $value;
		return $this;
	}  
}
?>