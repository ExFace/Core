<?php
namespace exface\Core\Formulas;

/**
 * This formula can be used to translate certain widget-attributes, e.g. caption.
 * 
 * @author SFL
 *
 */
class Translate extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     * Translates the passed messageId using the translator of the passed app and the passed values for
     * placeholders and plurification.
     * 
     * The $placeholderValues has to be a string, e.g. "%placeholder1%=>value1|%placeholder2%=>value2".
     * The pluralNumber has to be a string, e.g. "5".
     * 
     * @param string $appAlias
     * @param string $messageId
     * @param string $placeholderValues
     * @param string $pluralNumber
     * @return string
     */
    public function run(string $appAlias, string $messageId, string $placeholderValues = null, string $pluralNumber = null)
    {
        try {
            if ($placeholderValues) {
                $placeholder = $this->parsePlaceholderValues($placeholderValues);
            }
            if ($pluralNumber) {
                $plural = $this->parsePluralNumber($pluralNumber);
            }
            return $this->getWorkbench()->getApp($appAlias)->getTranslator()->translate($messageId, $placeholder, $plural);
        } catch (\Exception $e) {
            return $messageId;
        }
    }

    /**
     * Returns an array with keys and values parsed from the passed $input string.
     * 
     * e.g.:
     * $input: "%placeholder1%=>value1|%placeholder2%=>value2"
     * return: ["%placeholder1%" => "value1", "%placeholder2%" => "value2"]
     * 
     * @param string $input
     * @return string[]|null
     */
    private function parsePlaceholderValues(string $input)
    {
        // trennt die array-Elemente anhand der trennenden |
        $array1 = explode('|', trim($input));
        // trennt keys und values anhand der trennenden => und erzeugt ein array
        foreach ($array1 as $array1Value) {
            list($key, $value) = explode('=>', trim($array1Value));
            if ($key && $value) {
                $output[trim($key)] = trim($value);
            }
        }
        return $output;
    }

    /**
     * Returns the integer-value of the passed $input string.
     * 
     * @param string $input
     * @return number
     */
    private function parsePluralNumber(string $input)
    {
        return intval(trim($input));
    }
}
