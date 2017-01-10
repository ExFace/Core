<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Widgets\DebugMessage;

/**
 * This trait enables an exception to output data query specific debug information.
 *
 * @author Andrej Kabachnik
 *
 */
trait DataQueryExceptionTrait {
	
	use ExceptionTrait {
		create_debug_widget as parent_create_debug_widget;
	}
	
	private $query = null;
	
	public function __construct (DataQueryInterface $query, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_query($query);
	}
	
	/**
	 *
	 * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::get_query()
	 * @return DataQueryInterface
	 */
	public function get_query(){
		return $this->query;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::set_query()
	 */
	public function set_query(DataQueryInterface $query){
		$this->query = $query;
		return $this;
	}
	
	/**
	 * Exceptions for data queries can add extra tabs (e.g. an SQL-tab). Which tabs will be added depends on the implementation of
	 * the data query.
	 * 
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::create_debug_widget()
	 * 
	 * @param ErrorMessage
	 * @return ErrorMessage
	 */
	public function create_debug_widget(DebugMessage $error_message){
		$error_message = $this->parent_create_debug_widget($error_message);
		return $this->get_query()->create_debug_widget($error_message);
	}
	
}
?>