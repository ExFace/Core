<?php
namespace exface\Core\Communication\Recipients;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\Selectors\UserRoleSelectorInterface;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Communication\RecipientInterface;

class UserRoleRecipient implements RecipientGroupInterface
{    
    private $selector = null;
    
    private $users = null;
    
    /**
     * 
     * @param UserInterface $user
     */
    public function __construct(UserRoleSelectorInterface $roleSelector)
    {
        $this->selector = $roleSelector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientGroupInterface::getRecipients()
     */
    public function getRecipients(): array
    {
        if ($this->users === null) {
            $this->users = [];
            foreach ($this->getRecipientUids() as $uid) {
                $this->users[] = new UserRecipient(UserFactory::createFromUsernameOrUid($this->selector->getWorkbench(), $uid));
            }
        }
        return $this->users;
    }
    
    public function getRoleSelector() : UserRoleSelectorInterface
    {
        return $this->selector;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getRecipientUids() : array
    {
        $userData = DataSheetFactory::createFromObjectIdOrAlias($this->selector->getWorkbench(), 'exface.Core.USER');
        $userData->getColumns()->addFromUidAttribute();
        if ($this->selector->isAlias()) {
            $userData->getFilters()->addConditionFromString('USER_ROLE_USERS__USER_ROLE__ALIAS_WITH_NS', $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $userData->getFilters()->addConditionFromString('USER_ROLE_USERS__USER_ROLE', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        $userData->dataRead();
        return $userData->getUidColumn()->getValues(false);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientInterface::__toString()
     */
    public function __toString(): string
    {
        return 'role://' . $this->selector->toString();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientInterface::is()
     */
    public function is(RecipientInterface $otherRecipient): bool
    {
        if ($otherRecipient instanceof UserRoleRecipient) {
            $thisSel = $this->getRoleSelector();
            $otherSel = $otherRecipient->getRoleSelector();
            switch (true) {
                case $thisSel->isAlias() && $otherSel->isAlias():
                case $thisSel->isUid() && $otherSel->isUid():
                    return strcasecmp($thisSel->__toString(), $otherSel->__toString()) === 0;
                default:
                    // TODO How to compare a UID to an alias? Load data via DataSheet?
            }
        }
        return strcasecmp($this->__toString(), $otherRecipient->__toString()) === 0;
    }
}