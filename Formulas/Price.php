<?php namespace exface\Core\Formulas;

use exface\Core\Factories\DataTypeFactory;

/**
 * Formats prices based on a currency meta object.
 * Currency information is cached, so in general only one query per displayed
 * currency is needed.
 * @author Andrej Kabachnik
 *
 */
class Price extends \exface\Core\CommonLogic\Model\Formula {
	
	function run($price, $currency_oid = null, $decimals='', $dec_point='', $thousands_sep=''){
		if (!$price && $price !== 0) return;
		
		// TODO get the defaults from the meta object
		$decimals = $decimals ? $decimals : 2;
		$dec_point = $dec_point ? $dec_point : ',';
		$thousands_sep = $thousands_sep ? $thousands_sep : ' ';
		
		if ($currency_oid){
			$curr = $this->fetch_currency($currency_oid);
		}
		
		return number_format($price, $decimals, $dec_point, $thousands_sep) . ' ' . $curr['SUFFIX'];
	}
	
	private function fetch_currency($currency_oid){
		$exface = $this->get_workbench();
		if ($cache = $exface->data()->get_cache('currencies', $currency_oid)) return $cache;
		
		$curr = $exface->model()->get_object('ALEXA.RMS.CURR');
		$ds = $curr->create_data_sheet();
		$ds->get_columns()->add_from_expression('SUFFIX');
		$ds->add_filter_from_string('OID', $currency_oid);
		$ds->data_read();
		$currency = $ds->get_row();
		
		$exface->data()->set_cache('currencies', $currency_oid, $currency);
		
		return $currency;
	}
	
	public function get_data_type(){
		$exface = $this->get_data_sheet()->get_workbench();
		return DataTypeFactory::create_from_alias($exface, EXF_DATA_TYPE_PRICE);
	}
}
?>