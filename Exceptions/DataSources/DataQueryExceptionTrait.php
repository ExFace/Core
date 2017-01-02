<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\ErrorMessage;

trait DataQueryExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $query = null;
	
	public function __construct (DataQueryInterface $query, $message, $code = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_query($query);
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::get_query()
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
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::create_widget()
	 * 
	 * @param UiPageInterface $page
	 * @return ErrorMessage
	 */
	public function create_widget(UiPageInterface $page){
		$error_message = $this->create_parent_widget($page);
		return $this->get_query()->create_debug_widget($error_message);
	}
	
}
?>