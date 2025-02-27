<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\StringDataType;

/**
 * Transliterates the given string according to ICU rules
 * 
 * - `Transliterate('Änderung')` -> Anderung
 * - `Transliterate('Änderung', ':: Any-Latin; :: Latin-ASCII; :: Lower()')` -> anderung
 * - `Transliterate('ä/B', ':: Any-Latin; [:Punctuation:] Remove;')` -> a b
 * - `Transliterate('Aufgaben im Überblick', ':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;)` -> aufgaben im uberblick
 * 
 * See documentation of rules at https://unicode-org.github.io/icu/userguide/transforms/general/.
 * 
 * Examples are available here: https://www.php.net/manual/en/transliterator.transliterate.php.
 * 
 * @author Andrej Kabachnik
 *        
 */
class Transliterate extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $string = null, string $translitRules = ':: Any-Latin; :: Latin-ASCII;', $forward = true)
    {
        if ($string === null || $string === '') {
            return $string;
        }
        $trans = StringDataType::transliterate($string, $translitRules, $forward === true ? \Transliterator::FORWARD : \Transliterator::REVERSE);
        return trim($trans);
    }
}