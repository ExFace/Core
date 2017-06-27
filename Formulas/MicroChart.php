<?php
namespace exface\Core\Formulas;

/**
 * Creates an HTML-Progressbar taking the text, value and a max value as parameters.
 *
 * @author Andrej Kabachnik
 *        
 */
class MicroChart extends \exface\Core\CommonLogic\Model\Formula
{

    private $range_max = 0;

    function run($data, $chart_type = 'line')
    {
        $id = str_replace('.', '', uniqid(null, true));
        $vals = explode(',', $data);
        foreach ($vals as $val) {
            $val = intval(trim($val));
            if ($val > $this->range_max)
                $this->range_max = $val;
        }
        return "<span id='" . $id . "'>...wird geladen...</span>\n<script type='text/javascript'>\n$(function(){\n$('#" . $id . "').sparkline([" . $data . "],{'type':'" . $chart_type . "', chartRangeMax: " . $this->range_max . "});\n$.sparkline_display_visible();\n});\n</script>";
    }
}
?>