<?php namespace exface\Core\Interfaces;

interface TranslationInterface {

	/**
	 *
	 * @param string $message_id
	 * @param array $placeholder_values
	 * @return string
	 */
	public function translate($message_id, array $placeholder_values = null);

	/**
	 *
	 * @param string $message_id
	 * @param number $number
	 * @param array $placeholder_values
	 * @return string
	 */
	public function translate_plural($message_id, $number, array $placeholder_values = null);

	/**
	 * @return string
	 */
	public function get_locale();

	/**
	 *
	 * @param string $string
	 * @return TranslationInterface
	 */
	public function set_locale($string);

	/**
	 * Returns an array of fallback locales
	 * @return array
	 */
	public function get_fallback_locales();

	/**
	 * Sets a fallback locale. If other fallbacks are already defined (e.g. taken from the translation files), the given
	 * locale will be placed at the beginning of the list. The others will be kept. To replace the entire fallback list
	 * use set_fallback_locales().
	 *
	 * @param string $string
	 * @return TranslationInterface
	 */
	public function set_fallback_locale($string);

	/**
	 * Replaces the fallback locale list with the given array. In case of a fallback, the array will be searched starting
	 * from the first locale: the first translation found will be returned.
	 * @param array $locale_strings
	 * @return TranslationInterface
	 */
	public function set_fallback_locales(array $locale_strings);

	/**
	 *
	 * @param string $absolute_path
	 * @param string $locale
	 */
	public function add_dictionary_from_file($absolute_path, $locale);

}


?>