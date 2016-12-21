<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Exceptions\ExceptionTrait;

trait DataQueryExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $query = null;
	
	public function __construct (DataQueryInterface $query, $message, $code, $previous = null) {
		parent::__construct($message, $code, $previous);
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
	
	public function create_widget(UiPageInterface $page){
		/* @var $tabs \exface\Core\Widgets\Tabs */
		$tabs = $this->create_parent_widget($page);
		foreach ($this->get_query()->get_debug_panels($page, $tabs) as $panel){
			$tabs->add_tab($panel);
		}
		return $tabs;
	}
	
}
?>