<?php
namespace exface\Core\CommonLogic\Translation;

use Symfony\Component\Translation\Loader\ArrayLoader;

/**
 * Simple class to translate using an array dictionary, but with all the translation goodies like plurals, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class TranslationsArray extends Translation
{
    /**
     * 
     * @param string $locale
     * @param string[] $dictionary
     */
    public function __construct(string $locale, array $dictionary)
    {
        parent::__construct($locale);
        $this->getTranslator()->addLoader('array', new ArrayLoader());
        $this->getTranslator()->addResource('array', $dictionary, $locale);
    }
}