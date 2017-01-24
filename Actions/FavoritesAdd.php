<?php namespace exface\Core\Actions;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class FavoritesAdd extends ObjectBasketAdd {

	public function get_scope(){
		$this->set_scope('User');
		return parent::get_scope();
	}

}
?>