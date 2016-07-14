<?php namespace exface\Core\Formulas;

/**
 * Creates an HTML-Progressbar taking the text, value and a max value as parameters.
 * @author Andrej Kabachnik
 *
 */
class Progressbar extends \exface\Core\CommonLogic\Model\Formula {
	
	function run($caption, $value, $max=100){
		if (!$value) $value = $caption;
		if (!$value) return '';
		$return = '<div style="width:100%;border:1px solid #ccc;position:relative;">' .
    			'<div style="width:' . ($value ? $value / $max * 100 : 0) . '%;background:#' . ($value == 90 ? 'ddd' : 'BFD297') . ';">&nbsp;</div>' .
				'<div style="position:absolute; left:0; top:0; z-index:100; padding:0 3px; width:100%">' . $caption . '</div>' .
    			'</div>';
        return $return;
	}
}
?>