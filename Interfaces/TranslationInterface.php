<?php
namespace exface\Core\Interfaces;

interface TranslationInterface
{

    /**
     *
     * @param string $message_id            
     * @param array $placeholder_values            
     * @param float $plural_number            
     * @return string
     */
    public function translate($message_id, array $placeholder_values = null, $plural_number = null);

    /**
     *
     * @return string
     */
    public function getLocale();

    /**
     *
     * @param string $string            
     * @return TranslationInterface
     */
    public function setLocale($string);

    /**
     * Returns an array of fallback locales
     *
     * @return array
     */
    public function getFallbackLocales();

    /**
     * Sets a fallback locale.
     * If other fallbacks are already defined (e.g. taken from the translation files), the given
     * locale will be placed at the beginning of the list. The others will be kept. To replace the entire fallback list
     * use set_fallback_locales().
     *
     * @param string $string            
     * @return TranslationInterface
     */
    public function setFallbackLocale($string);

    /**
     * Replaces the fallback locale list with the given array.
     * In case of a fallback, the array will be searched starting
     * from the first locale: the first translation found will be returned.
     *
     * @param array $locale_strings            
     * @return TranslationInterface
     */
    public function setFallbackLocales(array $locale_strings);

    /**
     *
     * @param string $absolute_path            
     * @param string $locale            
     * @return TranslationInterface
     */
    public function addDictionaryFromFile($absolute_path, $locale);

    /**
     * Returns TRUE if there is a translation for the given message id and FALSE otherwise.
     *
     * @param string $message_id            
     * @return boolean
     */
    public function hasTranslation($message_id);
}

?>