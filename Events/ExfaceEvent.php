<?php

namespace exface\Core\Events;

use Symfony\Component\EventDispatcher\Event;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\CommonLogic\NameResolver;

class ExfaceEvent extends Event implements EventInterface
{

    private $exface = null;

    private $name = null;

    private $namespace = null;

    public function __construct(Workbench $exface)
    {
        $this->exface = $exface;
    }

    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Events\EventInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Events\EventInterface::setName()
     */
    public function setName($value)
    {
        $this->name = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Events\EventInterface::getNameWithNamespace()
     */
    public function getNameWithNamespace()
    {
        return $this->getNamespace() . NameResolver::NAMESPACE_SEPARATOR . $this->getName();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Events\EventInterface::getNamespace()
     */
    public function getNamespace()
    {
        return 'exface' . NameResolver::NAMESPACE_SEPARATOR . 'Core';
    }
}