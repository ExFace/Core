<?php
namespace exface\Apps\exface\Core\Actions;
class DuplicateObjectDialog extends EditObjectDialog {
	
	protected function init(){
		parent::init();
		$this->set_icon_name('copy');
		$this->set_save_action('exface.Core.CreateData');
	}
	
	/**
	 * {@inheritDoc}
	 * 
	 * In the case of the dublicate-action we need to remove the UID column from the data sheet to ensure, that the
	 * duplicated object will get new ids.
	 * 
	 * @see \exface\Apps\exface\Core\Actions\ShowWidget::get_prefill_data_sheet()
	 */
	protected function prefill_widget(){
		$data_sheet = $this->get_input_data_sheet();
		
		if ($data_sheet->get_uid_column()){
			$data_sheet = $this->get_widget()->prepare_data_sheet_to_prefill($data_sheet);
			if (!$data_sheet->is_up_to_date()){
				$data_sheet->add_filter_from_column_values($data_sheet->get_uid_column());
				$data_sheet->data_read();
			}
			$data_sheet->get_columns()->remove_by_key($data_sheet->get_uid_column()->get_name());
		}
		
		$this->get_widget()->prefill($data_sheet);
	}
}
?>