<?php
namespace exface\Core\Uxon;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\Communication\AbstractMessage;
use exface\Core\Factories\CommunicationFactory;

/**
 * UXON-schema class for communication messages.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class CommunicationMessageSchema extends UxonSchema
{
    public static function getSchemaName() : string
    {
        return '\\' . self::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPrototypeClass()
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : string
    {
        $name = $rootPrototypeClass ?? $this->getDefaultPrototypeClass();
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'channel') === 0) {
                $w = $this->getPrototypeClassFromSelector($value);
                if ($this->validatePrototypeClass($w) === true) {
                    $name = $w;
                }
                break;
            }
        }
        
        if (count($path) > 1) {
            return parent::getPrototypeClass($uxon, $path, $name);
        }
        
        return $name;
    }
    
    /**
     * Returns the prototype class for a given action selector (e.g. alias).
     *
     * @param string $selectorString
     * @return string
     */
    protected function getPrototypeClassFromSelector(string $selectorString) : string
    {
        try {
            $channel = CommunicationFactory::createChannelFromString($this->getWorkbench(), $selectorString);
            $message = CommunicationFactory::createMessageFromPrototype($this->getWorkbench(), $channel->getMessagePrototypeSelector());
        } catch (\Throwable $e) {
            $ex = new RuntimeException('Error loading message autosuggest - falling back to "AbstractMessage"!', null, $e);
            $this->getWorkbench()->getLogger()->logException($ex, LoggerInterface::DEBUG);
            return $this->getDefaultPrototypeClass();
        }
        return get_class($message);
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractMessage::class;
    }
}