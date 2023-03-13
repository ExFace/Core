<?php
namespace exface\Core\CommonLogic\Translation;

use exface\Core\Interfaces\TranslationInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Allows to translate all properties of a UXON object if there are corresponding keys 
 * in the dictionary of the given translation instance.
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonTranslator
{
    private $translation = null;
    /**
     * 
     * @param string $locale
     * @param string[] $dictionary
     */
    public function __construct(TranslationInterface $translation)
    {
        $this->translation = $translation;
    }
    
    /**
     * Returns a copy of the UXON object with all properties translated, which match keys in the dictionary
     * 
     * @param UxonObject $uxon
     * @param string $domain
     * @param string $namespace
     * @return UxonObject
     */
    public function translateUxonProperties(UxonObject $uxon, string $domain, string $namespace) : UxonObject
    {
        $uxon = $uxon->copy();
        
        if ($uxon->isEmpty()) {
            return $uxon;
        }
        
        if (! $this->translation->hasTranslationDomain($domain)) {
            return $uxon;
        }
        
        foreach ($uxon->getPropertiesAll() as $prop => $val) {
            if (is_string($val)) {
                $key = Translation::buildTranslationKey([$namespace, $prop, $val]);
                if (($trans = $this->translation->translate($key, null, null, $domain)) !== $key) {
                    $uxon->setProperty($prop, $trans);
                }
            }
            
            if ($val instanceof UxonObject) {
                $uxon->setProperty($prop, $this->translateUxonProperties($val, $domain, $namespace));
            }
        }
        
        return $uxon;
    }
}