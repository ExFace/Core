<?php namespace exface\Core\Actions;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class FavoritesFetch extends FavoritesAdd {
	
	protected function init(){
		parent::init();
	}	

	public function get_scope(){
		if (!parent::get_scope()){
			$this->set_scope('Window');
		}
		return parent::get_scope();
	}
	
	protected function perform(){
		$this->set_result($this->get_context()->export_uxon_object()->to_json());
	}

}
?>