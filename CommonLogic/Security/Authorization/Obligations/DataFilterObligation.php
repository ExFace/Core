<?php
namespace exface\Core\CommonLogic\Security\Authorization\Obligations;

use exface\Core\Interfaces\Security\ObligationInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;

/**
 * Obligation telling an authorization point to add certain filters to data 
 *
 * @author Andrej Kabachnik
 *
 */
class DataFilterObligation implements ObligationInterface
{
    private $condGrp = null;
    
    private $fulfilled = false;
    
    public function __construct(ConditionGroupInterface $conditionGroup)
    {
        $this->condGrp = $conditionGroup;
    }
    
    /**
     * 
     * @return ConditionGroupInterface
     */
    public function getConditionGroup() : ConditionGroupInterface
    {
        return $this->condGrp;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\ObligationInterface::isFulfilled()
     */
    public function isFulfilled(): bool
    {
        return $this->fulfilled;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\ObligationInterface::setFulfilled()
     */
    public function setFulfilled(bool $trueOrFalse): ObligationInterface
    {
        $this->fulfilled = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\ObligationInterface::getExplanation()
     */
    public function getExplanation(): string
    {
        return 'Filter "' . $this->getConditionGroup()->__toString() . '" ' . ($this->isFulfilled() ? '' : 'NOT ') . 'applied';
    }
}