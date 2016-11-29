<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\TranslationInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\TranslatorInterface;

class Translation implements TranslationInterface {
	
	private $exface = null;
	private $locale = null;
	private $translator = null;
	
	/**
	 * @deprecated use TranslationFactory instead!
	 * @param Workbench $workbench
	 */
	public function __construct(Workbench &$workbench){
		$this->exface = $workbench;
	}
	
	
	public function get_locale() {
		return $this->locale;
	}
	
	public function set_locale($string) {
		$this->locale = $string;
		$this->translator = new Translator($string, new MessageSelector());
		return $this;
	}
	
	/**
	 * @return TranslatorInterface
	 */
	protected function get_translator(){
	
	}
	
	public function translate($message, array $placeholder_values){
		return $this->get_translator()->trans($message, $placeholder_values);
	}
			
}


?>