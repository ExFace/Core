<?php
namespace exface\Core\Formulas;

class HtmlImage extends \exface\Core\CommonLogic\Model\Formula
{

    function run($url, $properties = null, $placeholder_url = null)
    {
        if (! $url)
            $url = $placeholder_url;
        
        return '<img src="' . $url . '" ' . $properties . ' />';
    }
}
?>