<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\Interfaces\Selectors\UserRoleSelectorInterface;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\ConditionGroupFactory;

/**
 * Representation of an Exface user.
 * 
 * @author SFL
 *
 */
class User implements UserInterface
{
    private $exface;

    private $dataSheet;

    private $uid;

    private $username;
    
    private $password;

    private $firstname;

    private $lastname;

    private $locale;

    private $email;

    private $anonymous = false;
    
    private $modelLoader = null;
    
    private $modelLoaded = false;
    
    private $roleSelectors = null;
    
    private $roleData = null;
    
    private $disabled = false;
    
    private $disabledCommunication = false;

    /**
     * 
     * @deprecated use UserFactory instead!
     * @param Workbench $exface
     * @param DataSheetInterface $dataSheet
     * @param boolean $anonymous
     */
    public function __construct(Workbench $exface, string $username = null, ModelLoaderInterface $loader = null)
    {
        $this->exface = $exface;
        $this->anonymous = ($username === null);
        if ($username !== null) {
            $this->username = $username;
        }
        $this->modelLoader = $loader;
    }
    
    protected function loadData() : User
    {
        if ($this->modelLoader !== null || $this->isAnonymous()) {
            $this->modelLoader->loadUserData($this);
            $this->modelLoaded = true;
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getUid()
     */
    public function getUid()
    {
        if ($this->uid === null && $this->modelLoaded === false) {
            $this->loadData();
        }
        if ($this->isAnonymous()) {
            return UserSelector::ANONYMOUS_USER_OID;
        }
        return $this->uid;
    }
    
    public function setUid(string $value) : UserInterface
    {
        $this->uid = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getUsername()
     */
    public function getUsername() : ?string
    {
        return $this->username;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::setUsername()
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getFirstName()
     */
    public function getFirstName()
    {
        if ($this->firstname === null && $this->modelLoaded === false) {
            $this->loadData();
        }
        return $this->firstname;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::setFirstName()
     */
    public function setFirstName($firstname)
    {
        $this->firstname = $firstname;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getLastName()
     */
    public function getLastName()
    {
        if ($this->lastname === null && $this->modelLoaded === false) {
            $this->loadData();
        }
        return $this->lastname;
    }
    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getName()
     */
    public function getName() : string
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::setLastName()
     */
    public function setLastName($lastname)
    {
        $this->lastname = $lastname;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getLocale()
     */
    public function getLocale()
    {
        if ($this->locale === null && $this->modelLoaded === false) {
            $this->loadData();
        }
        
        if (! $this->locale) {
            $this->locale = $this->getWorkbench()->getConfig()->getOption("SERVER.DEFAULT_LOCALE");
        }
        
        return $this->locale;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::setLocale()
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getEmail()
     */
    public function getEmail()
    {
        if ($this->email === null && $this->modelLoaded === false) {
            $this->loadData();
        }
        return $this->email;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::setEmail()
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::exportDataSheet()
     */
    public function exportDataSheet()
    {
        $userModel = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        
        $userSheet = DataSheetFactory::createFromObject($userModel);
        if ($this->hasModel()) {
            foreach ($userModel->getAttributes() as $attr) {
                $userSheet->getColumns()->addFromAttribute($attr);
            }
            $userSheet->getFilters()->addConditionFromString('USERNAME', $this->getUsername(), EXF_COMPARATOR_EQUALS);
            $userSheet->dataRead();
        }
        
        if ($this->getUid()) {
            $userSheet->getColumns()->addFromAttribute($userModel->getUidAttribute());
            $userSheet->setCellValue($userModel->getUidAttributeAlias(), 0, $this->getUid());
        }
        if ($this->getUsername()) {
            $userSheet->getColumns()->addFromAttribute($userModel->getAttribute('USERNAME'));
            $userSheet->setCellValue('USERNAME', 0, $this->getUsername());
            // Wichtig, da der Username auch das Label ist.
            $userSheet->setCellValue('LABEL', 0, $this->getUsername());
        }
        if ($this->getFirstName()) {
            $userSheet->getColumns()->addFromAttribute($userModel->getAttribute('FIRST_NAME'));
            $userSheet->setCellValue('FIRST_NAME', 0, $this->getFirstName());
        }
        if ($this->getLastName()) {
            $userSheet->getColumns()->addFromAttribute($userModel->getAttribute('LAST_NAME'));
            $userSheet->setCellValue('LAST_NAME', 0, $this->getLastName());
        }
        if ($this->getLocale()) {
            $userSheet->getColumns()->addFromAttribute($userModel->getAttribute('LOCALE'));
            $userSheet->setCellValue('LOCALE', 0, $this->getLocale());
        }
        if ($this->getEmail()) {
            $userSheet->getColumns()->addFromAttribute($userModel->getAttribute('EMAIL'));
            $userSheet->setCellValue('EMAIL', 0, $this->getEmail());
        }
        if ($pwd = $this->getPassword()) {
            $userSheet->getColumns()->addFromAttribute($userModel->getAttribute('PASSWORD'));
            $userSheet->setCellValue('PASSWORD', 0, $pwd);
        }
        
        $userSheet->getColumns()->addFromAttribute($userModel->getAttribute('DISABLED_FLAG'));
        if ($this->isDisabled()) {
            $userSheet->setCellValue('DISABLED_FLAG', 0, '1');
        } else {
            $userSheet->setCellValue('DISABLED_FLAG', 0, '0');
        }
        
        return $userSheet;
    }

    /**
     * @deprecated
     * TODO #nocms remove in favor of hasRole()
     */
    public function isUserAdmin()
    {
        if ($this->isAnonymous()) {
            return false;
        }return true;
        return $this->hasRole(new UserRoleSelector($this->getWorkbench(), 'exface.Core.SUPERUSER'));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserImpersonationInterface::isAnonymous()
     */
    public function isAnonymous() : bool
    {
        $this->anonymous = ($this->username === null);
        return $this->anonymous;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::is()
     */
    public function is(UserInterface $otheruser) : bool
    {
        return $this->getUsername() === $otheruser->getUsername();
    }
    
    public function hasModel() : bool
    {
        if ($this->modelLoader === null) {
            return false;
        }
        
        if ($this->modelLoaded === true) {
            return true;
        }
        
        try {
            $this->loadData();
        } catch (\exface\Core\Exceptions\UserNotFoundError $e) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getPassword()
     */
    public function getPassword() : ?string
    {
        if ($this->password === null && $this->modelLoaded === false) {
            $this->loadData();
        }
        return $this->password;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::setPassword()
     */
    public function setPassword(string $value) : UserInterface
    {
        $this->password = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getInitials()
     */
    public function getInitials() : string
    {
        if ($this->isAnonymous() === true) {
            return 'Guest';
        }
        $firstInitial = mb_substr($this->getFirstName(), 0, 1);
        $secondInitial = mb_substr($this->getLastName(), 0, 1);
        
        if (! $firstInitial && ! $secondInitial) {
            return $this->getUsername();
        }
        
        return ($firstInitial ? $firstInitial . '.' : '') . ($secondInitial ? $secondInitial . '.' : '');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::hasRole()
     */
    public function hasRole(UserRoleSelectorInterface $selector): bool
    {
        foreach ($this->getRoleSelectors() as $rs) {
            if (strcasecmp($rs->__toString(), $selector->__toString()) === 0) {
                return true;
            }
        }
        
        if ($selector->isAlias()) {
            $aliasCol = $this->getRoleData()->getColumns()->get('ALIAS_WITH_NS');
            return $aliasCol->findRowByValue($selector->toString()) !== false;
        }
        
        return false;
    }
    
    /**
     * 
     * @return UserRoleSelectorInterface[]
     */
    protected function getRoleSelectors() : array
    {
        if ($this->roleSelectors === null) {
            if ($this->modelLoaded === false) {
                $this->loadData();
            } else {
                $this->roleSelectors = [];
            }
        }
        if (empty($this->roleSelectors)) {
            $this->roleSelectors = $this->addBuiltInRoles($this->roleSelectors ?? []);
        }
        return $this->roleSelectors ?? [];
    }
    
    /**
     * 
     * @param UserRoleSelectorInterface[] $selectorArray
     * @return UserRoleSelectorInterface[]
     */
    protected function addBuiltInRoles(array $selectorArray) : array
    {
        if ($this->isAnonymous()) {
            $selectorArray[] = new UserRoleSelector($this->getWorkbench(), UserRoleSelector::ANONYMOUS_USER_ROLE_UID);
            $selectorArray[] = new UserRoleSelector($this->getWorkbench(), UserRoleSelector::ANONYMOUS_USER_ROLE_ALIAS);
        } else {
            $selectorArray[] = new UserRoleSelector($this->getWorkbench(), UserRoleSelector::AUTHENTICATED_USER_ROLE_UID);
            $selectorArray[] = new UserRoleSelector($this->getWorkbench(), UserRoleSelector::AUTHENTICATED_USER_ROLE_ALIAS);
        }
        return $selectorArray;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::addRoleSelector()
     */
    public function addRoleSelector($selectorOrString) : UserInterface
    {
        if (empty($this->roleSelectors)) {
            $this->roleSelectors = $this->addBuiltInRoles($this->roleSelectors ?? []);
        }
        if ($selectorOrString instanceof UserRoleSelectorInterface) {
            $this->roleSelectors[] = $selectorOrString;
        } else {
            $this->roleSelectors[] = new UserRoleSelector($this->getWorkbench(), $selectorOrString);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::setDisabled()
     */
    public function setDisabled(bool $trueOrFalse) : UserInterface
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::isDisabled()
     */
    public function isDisabled() : bool
    {
        if ($this->disabled === null && $this->modelLoaded === false) {
            $this->loadData();
        }
        return $this->disabled;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::isDisabledCommunication()
     */
    public function isDisabledCommunication() : bool
    {
        return $this->disabledCommunication;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::setDisabledCommunication()
     */
    public function setDisabledCommunication(bool $trueOrFalse) : UserInterface
    {
        $this->disabledCommunication = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getAttribute()
     */
    public function getAttribute(string $alias)
    {
        switch (mb_strtoupper($alias)) {
            case "ID":
            case "UID":
                return $this->getUid();
            case "USERNAME":
                return $this->getUsername();
            case "FULL_NAME":
            case "NAME":
                return $this->getName();
        }
        $userObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        $ds = DataSheetFactory::createFromObject($userObj);
        $col = $ds->getColumns()->addFromExpression($alias);
        $ds->getFilters()->addConditionFromString($userObj->getUidAttributeAlias(), $this->getUid(), ComparatorDataType::EQUALS);
        $ds->dataRead();
        return $col->getValue(0);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getStartPage()
     */
    public function getStartPage() : UiPageInterface
    {
        $selectors = $this->getRoleData()->getColumns()->getByExpression('START_PAGE')->getValues();
        foreach ($selectors as $selector) {
            if ($selector !== null && $selector !== '') {
                return UiPageFactory::createFromModel($this->getWorkbench(), $selector);
            }
        }
        return UiPageFactory::createIndexPage($this->getWorkbench());
    }
    
    public function getRoles(string $attributeAlias = 'ALIAS_WITH_NS') : array
    {
        $col = $this->getRoleData()->getColumns()->getByExpression($attributeAlias);
        if (! $col) {
            throw new RuntimeException('Cannot get roles of user "' . $this->getUsername() . '": requested role attribute "' . $attributeAlias . '" not found!');
        }
        return $col->getValues();
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function getRoleData() : DataSheetInterface
    {
        if ($this->roleData === null) {
            $roleUids = [];
            $roleAliases = [];
            foreach ($this->getRoleSelectors() as $sel) {
                if ($sel->isUid()) {
                    $roleUids[] = $sel->toString();
                } else {
                    $roleAliases[] = $sel->toString();
                }
            }
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE');
            if (! empty($roleUids)) {
                $ds->getFilters()->addConditionFromValueArray('UID', $roleUids);
            } else {
                $ds->getFilters()->addConditionFromString('USER_ROLE_USERS__USER', $this->getUid(), ComparatorDataType::EQUALS);
            }
            $ds->getColumns()->addFromAttributeGroup($ds->getMetaObject()->getAttributeGroup(AttributeGroup::ALL));
            $ds->dataRead();
            
            // Check if there are role aliases, that were not yet loaded and add them as additional filter if so.
            // This should happen really rarely - only if somebody added a role alias programmatically. Roles loaded
            // from the model will always have UIDs. Filtering by UID is faster, so we only do the alias-based
            // filtering if really neccessary.
            if (! empty($roleAliases)) {
                $missingAliases = array_diff($roleAliases, $ds->getColumns()->get('ALIAS_WITH_NS')->getValues(false));
                if (! empty($missingAliases)) {
                    $uidFilter = $ds->getFilters();
                    $orFilter = ConditionGroupFactory::createForDataSheet($ds, EXF_LOGICAL_OR);
                    $orFilter->addNestedGroup($uidFilter);
                    $orFilter->addConditionFromValueArray('ALIAS_WITH_NS', $missingAliases);
                    $ds->setFilters($orFilter);
                    $ds->dataRead();
                }
            }
            
            $this->roleData = $ds;
        }
        return $this->roleData;
    }
}