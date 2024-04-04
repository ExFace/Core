<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Inserts HTML line breaks before all newlines in a string 
 * 
 * @author ralf.mulansky
 *
 */
class Nl2br extends Formula
{
    public function run($string = null)
    {
        return nl2br($string);
    }
}