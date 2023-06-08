<?php
namespace exface\Core\Communication\Recipients;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\Communication\RecipientInterface;

/**
 * Allows to address users, that have multiple rows: e.g. `my.App.role1+my.App.role2`
 * 
 * @author Andrej Kabachnik
 *
 */
class UserMultiRoleRecipient implements RecipientGroupInterface
{    
    const ROLE_DELIMITER = '+';
    
    private $selectorString = null;
    
    private $recipients = null;
    
    private $workbench = null;
    
    /**
     * 
     * @param UserInterface $user
     */
    public function __construct(string $concatenatedSelectors, WorkbenchInterface $workbench)
    {
        $this->selectorString = $concatenatedSelectors;
        $this->workbench = $workbench;
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
                $this->users[] = new UserRecipient(UserFactory::createFromUsernameOrUid($this->workbench, $uid));
            }
        }
        return $this->users;
    }
    
    /**
     * 
     * @return array
     */
    protected function getRecipientUids() : array
    {
        $userData = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.USER');
        $userData->getColumns()->addFromUidAttribute();
        $roles = array_filter(explode(self::ROLE_DELIMITER, $this->selectorString));
        foreach ($roles as $role) {
            $role = trim($role);
            $roleSel = new UserRoleSelector($this->workbench, $role);
            if ($roleSel->isAlias()) {
                $userData->getFilters()->addConditionFromString('USER_ROLE_USERS__USER_ROLE__ALIAS_WITH_NS', $role, ComparatorDataType::EQUALS);
            } else {
                $userData->getFilters()->addConditionFromString('USER_ROLE_USERS__USER_ROLE', $role, ComparatorDataType::EQUALS);
            }
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
        return 'role://' . $this->selectorString;
    }
    
    /**
     * 
     * @param string $string
     * @return bool
     */
    public static function isMultipleRoles(string $string) : bool
    {
        return strpos($string, self::ROLE_DELIMITER) !== false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientInterface::is()
     */
    public function is(RecipientInterface $otherRecipient): bool
    {
        return strcasecmp($this->__toString(), $otherRecipient->__toString()) === 0;
    }
}