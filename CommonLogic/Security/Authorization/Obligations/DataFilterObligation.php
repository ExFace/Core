<?php
namespace exface\Core\CommonLogic\Security\Authorization\Obligations;

use exface\Core\Interfaces\Security\ObligationInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;

/**
 * Obligation telling an authorization point to add certain filters to data.
 * 
 * If multiple filter obligation are applied at the same time, they will be
 * combined by an OR unless they have explicit scopes defined. Using scopes
 * will produce multiple OR-groups combined by an AND. The scope itself is
 * simply a string an ca be anythings. Scopes are set by the policy and need
 * to be evaluated by the authorization point.
 *
 * @author Andrej Kabachnik
 *
 */
class DataFilterObligation implements ObligationInterface
{
    private $condGrp = null;
    
    private $fulfilled = false;
    
    private $scope = null;
    
    /**
     * 
     * @param ConditionGroupInterface $conditionGroup
     * @param string $scope
     */
    public function __construct(ConditionGroupInterface $conditionGroup, string $scope = null)
    {
        $this->condGrp = $conditionGroup;
        $this->scope = $scope;
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
     * @return string|NULL
     */
    public function getScope() : ?string
    {
        return $this->scope;
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
        return 'Filter "' . $this->getConditionGroup()->__toString() . '" ' . ($this->isFulfilled() ? '' : 'NOT ') . 'applied' . ($this->scope !== null ? ' in scope "' . $this->scope . '"': '');
    }
}