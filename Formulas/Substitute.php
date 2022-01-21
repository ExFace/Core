<?php
namespace exface\Core\Formulas;

/**
 * Replaces a set of characters with another.
 * E.g. SUBSTITUTE('asdf', 'df', 'as') = 'asas'
 *
 * @author Andrej Kabachnik
 *        
 */
class Substitute extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    function run($text = null, $search = null, $replace = null)
    {
        if ($text === null || $text === '') {
            return $text;
        }
        return str_replace($search, $replace, $text);
    }
}
?>