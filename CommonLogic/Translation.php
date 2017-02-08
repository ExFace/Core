<?php namespace exface\Core\CommonLogic;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use exface\Core\Interfaces\TranslationInterface;

class Translation implements TranslationInterface {
	
	private $locale = null;
	private $translator = null;
		
	public function get_locale() {
		return $this->translator->getLocale();
	}
	
	public function set_locale($string) {
		$this->translator = new Translator($string);
		$this->translator->addLoader('json', new JsonFileLoader());
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TranslationInterface::get_fallback_locales()
	 */
	public function get_fallback_locales() {
		return $this->translator->getFallbackLocales();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TranslationInterface::set_fallback_locale()
	 */
	public function set_fallback_locale($string) {
		$locales = $this->translator->getFallbackLocales();
		$this->translator->setFallbackLocales(array_unshift($locales, $string));
		return $this;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TranslationInterface::set_fallback_locales()
	 */
	public function set_fallback_locales(array $locale_strings){
		$this->translator->setFallbackLocales($locale_strings);
		return $this;
	}
	
	public function add_dictionary_from_file($absolute_path, $locale){
		if (file_exists($absolute_path)){
			$this->translator->addResource('json', $absolute_path, $locale);
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TranslationInterface::translate()
	 */
	public function translate($message_id, array $placeholder_values = null, $plural_number = null){
		if (is_null($plural_number)){
			return $this->get_translator()->trans($message_id, is_null($placeholder_values) ? array() : $placeholder_values);
		} else {
			return $this->get_translator()->transChoice($message_id, $plural_number, is_null($placeholder_values) ? array() : $placeholder_values);
		}
	}
	
	/**
	 * @return TranslatorInterface
	 */
	protected function get_translator(){
		return $this->translator;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TranslationInterface::has_translation()
	 */
	public function has_translation($message_id){
		return $this->tranlate($message_id) === $message_id ? false : true;
	}
	
}


?>