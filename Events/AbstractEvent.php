<?php
namespace exface\Core\Events;

use Symfony\Contracts\EventDispatcher\Event;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * Default implementation of the alias methods for events.
 * 
 * NOTE: the default implementation of the static method getEventName() seems to be not
 * very performant: it is recommended to override it with a simple method returning 
 * a static string if the event is expected to be fired often.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractEvent extends Event implements EventInterface
{
    private $alias = null;
    
    private $namespace = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Events\EventInterface::getAlias()
     */
    public function getAlias()
    {
        if ($this->alias === null) {
            list($this->namespace, $this->alias) = $this::getAliasFromClass();
        }
        return $this->alias;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Events\EventInterface::getNameWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Events\EventInterface::getNamespace()
     */
    public function getNamespace()
    {
        if ($this->namespace === null) {
            list($this->namespace, $this->alias) = $this::getAliasFromClass();
        }
        return $this->namespace;
    }
    
    /**
     * NOTE: this default implementation of this method seems to be not
     * very performant: it is recommended to override it with a simple method returning 
     * a static string if the event is expected to be fired often.
     * 
     * @see EventInterface::getEventName()
     */
    public static function getEventName() : string
    {
        list($namespace, $alias) = static::getAliasFromClass();
        return $namespace . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $alias;
    }
    
    /**
     * @return string[]
     */
    private static function getAliasFromClass() : array
    {
        $delim = AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;
        $name = str_replace('\\', $delim, get_called_class());
        
        // exface\Core\Events\DataSheet\OnReadEvent -> exface.Core.DataSheet
        $namespace = StringDataType::substringBefore($name, $delim, '', false, true);
        $namespace = str_replace($delim . 'Events' . $delim, $delim, $namespace);
        
        // exface\Core\Events\DataSheet\OnReadEvent -> OnRead
        $alias = StringDataType::substringAfter($name, $delim, '', false, true);
        if (substr($alias, -5) === 'Event') {
            $alias = substr($alias, 0, -5);
        }
        
        return [$namespace, $alias];
    }
}