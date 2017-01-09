<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DebuggerInterface;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class Debugger implements DebuggerInterface {
	
	private $prettify_errors = false;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DebuggerInterface::print_exception()
	 */
	public function print_exception(\Throwable $exception, $use_html = true){
		$handler = new ExceptionHandler();
		$flattened_exception = FlattenException::create($exception);
		$output = "<style>" . $handler->getStylesheet($flattened_exception) . "</style>" . $handler->getContent($flattened_exception);
		return $output;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DebuggerInterface::get_prettify_errors()
	 */
	public function get_prettify_errors() {
		return $this->prettify_errors;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DebuggerInterface::set_prettify_errors()
	 */
	public function set_prettify_errors($value) {
		$this->prettify_errors = $value ? true : false;
		if ($this->prettify_errors){
			$this->register_handler();
		}
		return $this;
	}
	
	protected function register_handler(){
		//Debug::enable(E_ALL & ~E_NOTICE);
		ExceptionHandler::register();
		ErrorHandler::register();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DebuggerInterface::print_variable()
	 */
	public function print_variable($anything, $use_html = true){
		$cloner = new VarCloner();
		if ($use_html){
			$dumper = new HtmlDumper();
			$dumper->setDisplayOptions(array('maxDepth' => 5));
		} else {
			$dumper = new CliDumper();
		}
		return $dumper->dump($cloner->cloneVar($anything), true);
	}
	  
}
