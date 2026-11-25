<?php
namespace exface\Core\Uxon;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Selectors\SelectorInvalidError;
use exface\Core\CommonLogic\Communication\AbstractMessage;
use exface\Core\Factories\CommunicationFactory;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\Exceptions\Communication\CommunicationTemplateNotFoundError;
use exface\Core\Exceptions\Communication\CommunicationChannelNotFoundError;

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
                $p = $this->getPrototypeClassFromChannel($value);
                if ($p !== null && $this->validatePrototypeClass($p) === true) {
                    $name = $p;
                }
                break;
            }
            if (strcasecmp($key, 'template') === 0) {
                $p = $this->getPrototypeClassFromTemplate($value);
                if ($p !== null && $this->validatePrototypeClass($p) === true) {
                    $name = $p;
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
     * 
     * @param string $selectorString
     * @return string|NULL
     */
    protected function getPrototypeClassFromTemplate(string $selectorString) : ?string
    {
        try {
            $selectorString = trim($selectorString);
            $tpl = CommunicationFactory::createTemplatesFromModel($this->getWorkbench(), [$selectorString])[0];
        } catch (CommunicationTemplateNotFoundError | SelectorInvalidError $e) {
            $this->handleIncompleteInputError($e);
            return null;
        }
        if ($channelSelector = $tpl->getChannelSelector()) {
            return $this->getPrototypeClassFromChannel($channelSelector);
        }
        return null;
    }
    
    /**
     * Returns the prototype class for a given action selector (e.g. alias).
     *
     * @param string|CommunicationChannelSelectorInterface $selectorOrString
     * @return string|NULL
     */
    protected function getPrototypeClassFromChannel($selectorOrString) : ?string
    {
        try {
            if ($selectorOrString instanceof CommunicationChannelSelectorInterface) {
                $channel = CommunicationFactory::createFromSelector($selectorOrString);
            } else {
                $channel = CommunicationFactory::createChannelFromString($this->getWorkbench(), trim($selectorOrString));
            }
        } catch (CommunicationChannelNotFoundError | SelectorInvalidError $e) {
            if ($selectorOrString instanceof CommunicationChannelSelectorInterface) {
                throw $e;
            }
            $this->handleIncompleteInputError($e);
            return null;
        }
        $message = CommunicationFactory::createMessageFromPrototype($this->getWorkbench(), $channel->getMessagePrototypeSelector());
        return get_class($message);
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractMessage::class;
    }
}