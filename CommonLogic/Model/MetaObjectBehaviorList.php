<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaObjectBehaviorList extends EntityList implements BehaviorListInterface
{
    private $autoregisterBehaviors = false;

    public function __construct(WorkbenchInterface $exface, $parent_object, $autoregisterBehaviors = false)
    {
        parent::__construct($exface, $parent_object);
        $this->autoregisterBehaviors = $autoregisterBehaviors;
    }

    /**
     * A behavior list will activate every behavior right after it has been added
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::add()
     * @param BehaviorInterface $behavior            
     */
    public function add($behavior, $uid = null)
    {
        if (! $behavior->getObject()->isExactly($this->getParent())) {
            $behavior->setObject($this->getParent());
        }
        $result = parent::add($behavior, $uid);
        if (! $behavior->isDisabled() && $this->autoregisterBehaviors === true) {
            $behavior->register();
        }
        return $result;
    }

    /**
     *
     * @return MetaObjectInterface
     */
    public function getObject()
    {
        return $this->getParent();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\BehaviorListInterface::getByAlias()
     */
    public function getByAlias(string $qualified_alias) : BehaviorListInterface
    {
        $result = new self($this->getWorkbench(), $this->getObject());
        foreach ($this->getAll() as $key => $behavior) {
            if (strcasecmp($behavior->getAliasWithNamespace(), $qualified_alias) === 0) {
                $result->add($behavior, $key);
            }
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\BehaviorListInterface::getByUid()
     */
    public function getByUid(string $uid) : ?BehaviorInterface
    {
        return $this->get($uid);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\BehaviorListInterface::getByPrototypeClass($className)
     */
    public function getByPrototypeClass(string $className) : BehaviorListInterface
    {
        $result = new self($this->getWorkbench(), $this->getObject());
        foreach ($this->getAll() as $key => $behavior) {
            if ($behavior instanceof $className) {
                $result->add($behavior, $key);
            }
        }
        return $result;
    }

    /**
     *
     * @param MetaObjectInterface $value            
     * @return MetaObjectBehaviorList
     */
    public function setObject(MetaObjectInterface $value)
    {
        $this->setParent($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::setParent()
     * @param
     *            Object
     */
    public function setParent($object)
    {
        $result = parent::setParent($object);
        foreach ($this->getAll() as $behavior) {
            $behavior->setObject($object);
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function findBehavior(string $class, bool $allowMultiple = false): ?BehaviorInterface
    {
        $hits = $this->getByPrototypeClass($class);
        if ($hits->isEmpty()) {
            return null;
        }

        if (!$allowMultiple && $hits->count() > 1) {
            throw new RuntimeException('Only one behavior of type "' . $class . '" allowed!');
        }

        return $hits->getFirst();
    }
}