<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\AliasTrait;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractBehavior implements BehaviorInterface
{
    use ImportUxonObjectTrait;
    use AliasTrait;
    
    private $object = null;
    
    private $selector = null;

    private $behavior = null;

    private $disabled = false;

    private $registered = false;

    private $name_resolver = false;

    public function __construct(BehaviorSelectorInterface $selector, MetaObjectInterface $object = null)
    {
        $this->object = $object;
        $this->selector = $selector;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::getSelector()
     */
    public function getSelector() : BehaviorSelectorInterface
    {
        return $this->selector;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::getObject()
     */
    public function getObject() : MetaObjectInterface
    {
        return $this->object;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::setObject()
     */
    public function setObject(MetaObjectInterface $object) : BehaviorInterface
    {
        $this->object = $object;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     * @return WorkbenchInterface
     */
    public function getWorkbench()
    {
        return $this->getObject()->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('disabled', $this->isDisabled());
        return $uxon;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::activate()
     */
    abstract public function register() : BehaviorInterface;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::isDisabled()
     */
    public function isDisabled() : bool
    {
        return $this->disabled;
    }

    /**
     * This method does the same as enable() and disable().
     * It is important to be able to import UXON objects.
     *
     * @param bool $value
     * @return BehaviorInterface
     */
    public function setDisabled($value) : BehaviorInterface
    {
        $this->disabled = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::disable()
     */
    public function disable() : BehaviorInterface
    {
        $this->disabled = true;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::enable()
     */
    public function enable() : BehaviorInterface
    {
        if (! $this->isRegistered()) {
            $this->register();
        }
        $this->disabled = false;
        return $this;
    }

    /**
     * Marks the behavior as registered.
     * is_registered() will now return true. This is a helper method for
     * the case, if you don't want to override the is_registered() method: just call set_registered() in
     * your register() implementation!
     *
     * @param boolean $value            
     * @return BehaviorListInterface
     */
    protected function setRegistered(bool $value) : BehaviorInterface
    {
        $this->registered = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::isRegistered()
     */
    public function isRegistered() : bool
    {
        return $this->registered;
    }

    /**
     * Returns a copy of the Behavior without
     *
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     * @return BehaviorInterface
     */
    public function copy()
    {
        return clone $this;
    }
}