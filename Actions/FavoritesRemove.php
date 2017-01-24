<?php namespace exface\Core\Actions;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class FavoritesRemove extends FavoritesFetch {
	
	protected function perform(){
		$counter = 0;
		$input = $this->get_input_data_sheet();
		$object = $input->get_meta_object();
		if ($input->is_empty()){
			$this->get_context()->remove_instances_for_object_id($object->get_id());
		} else {
			// TODO remove single instances
		}
		$this->set_result($this->get_favorites_json());
	}
	
}
?>