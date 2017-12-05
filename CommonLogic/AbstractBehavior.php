<?php
namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;
use exface\Core\Interfaces\NameResolverInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractBehavior implements BehaviorInterface
{

    private $object = null;

    private $behavior = null;

    private $disabled = false;

    private $registered = false;

    private $name_resolver = false;

    public function __construct(MetaObjectInterface $object)
    {
        $this->setObject($object);
    }

    /**
     *
     * @return NameResolverInterface
     */
    public function getNameResolver()
    {
        return $this->name_resolver;
    }

    public function setNameResolver($value)
    {
        $this->name_resolver = $value;
        return $this;
    }

    public function getAlias()
    {
        return $this->getNameResolver()->getAlias();
    }

    public function getAliasWithNamespace()
    {
        return $this->getNameResolver()->getAliasWithNamespace();
    }

    public function getNamespace()
    {
        return $this->getNameResolver()->getNamespace();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::getObject()
     */
    public function getObject()
    {
        return $this->object;
    }

    public function setObject(MetaObjectInterface $value)
    {
        $this->object = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     * @return exface
     */
    public function getWorkbench()
    {
        return $this->getObject()->getWorkbench();
    }

    public function importUxonObject(UxonObject $uxon)
    {
        return $uxon->mapToClassSetters($this);
    }

    /**
     *
     * {@inheritdoc}
     *
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
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::activate()
     */
    abstract public function register();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::isDisabled()
     */
    public function isDisabled()
    {
        return $this->disabled;
    }

    /**
     * This method does the same as enable() and disable().
     * It is important to be able to import UXON objects.
     *
     * @param
     *            boolean
     * @return BehaviorInterface
     */
    public function setDisabled($value)
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
    public function disable()
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
    public function enable()
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
    protected function setRegistered($value)
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
    public function isRegistered()
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