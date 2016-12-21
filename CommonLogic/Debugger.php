<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DebuggerInterface;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

class Debugger implements DebuggerInterface {
	
	private $exception_handler = null;
	private $prettify_errors = false;
	
	public function get_exception_handler() {
		return $this->exception_handler;
	}
	
	public function set_exception_handler($value) {
		$this->exception_handler = $value;
		return $this;
	} 
	
	public function print_exception(\Throwable $exception){
		if ($this->get_exception_handler()){
			$this->get_exception_handler()->allowQuit(false);
			$this->get_exception_handler()->writeToOutput(false);
			return $this->get_exception_handler()->handleException($exception);
		} else {
			return $exception->getMessage();
		}
	}
	
	public function get_prettify_errors() {
		return $this->prettify_errors;
	}
	
	public function set_prettify_errors($value) {
		$this->prettify_errors = $value ? true : false;
		if ($this->prettify_errors){
			$this->register_handler();
		}
		return $this;
	}
	
	protected function register_handler(){
		$whoops = new Run();
		$whoops->pushHandler(new PrettyPageHandler());
		$whoops->register();
		$this->set_exception_handler($whoops);
	}
	  
}
