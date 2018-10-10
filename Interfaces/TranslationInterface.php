<?php
namespace exface\Core\Interfaces;

/**
 * Interface for a translation implementation of an app.
 * 
 * Apps are free to use any translation implementations as long as they (or a wrapper)
 * implement this interface. Thus, an app can define it's all translation resource
 * format and choose it's own translation engine.
 * 
 * @author Andrej Kabachnik
 *
 */
interface TranslationInterface
{
    /**
     *
     * @param string $message_id            
     * @param array $placeholder_values            
     * @param float $plural_number            
     * @return string
     */
    public function translate(string $message_id, array $placeholder_values = null, $plural_number = null) : string;

    /**
     *
     * @return string
     */
    public function getLocale() : string;

    /**
     * Returns an array of fallback locales
     *
     * @return array
     */
    public function getFallbackLocales() : array;

    /**
     * Returns TRUE if there is a translation for the given message id and FALSE otherwise.
     *
     * @param string $message_id            
     * @return boolean
     */
    public function hasTranslation($message_id) : bool;
    
    /**
     * @return string[]
     */
    public function getDictionary() : array;
}