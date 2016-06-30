<?php
namespace exface\Core\Actions;
class showHeaders extends ShowWidget {
	public function get_result_output(){
		$this->prefill_widget();
		return $this->get_app()->exface()->ui()->get_template()->draw_headers($this->get_widget());
	}
}
?>