<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\Interfaces\Selectors\UserRoleSelectorInterface;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\ModelLoaders\SqlModelLoader;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

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

    /**
     * 
     * @deprecated use UserFactory::create() instead!
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
        if ($this->modelLoader !== null) {
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
            $this->locale = $this->getWorkbench()->getConfig()->getOption("LOCALE.DEFAULT");
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
        }
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
        return mb_substr($this->getFirstName(), 0, 1) . '.' . mb_substr($this->getLastName(), 0, 1) . '.';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::hasRole()
     */
    public function hasRole(UserRoleSelectorInterface $selector): bool
    {
        foreach ($this->getRoleSelectors() as $rs) {
            if ($rs->__toString() === $selector->__toString()) {
                return true;
            }
        }
        
        // If the selector is an alias and it's not one of the built-in aliases, look up the
        // the UID and check that.
        if ($selector->isAlias() && $selector->toString() !== UserRoleSelector::AUTHENTICATED_USER_ROLE_ALIAS) {
            $appAlias = $selector->getAppAlias();
            $roleAlias = StringDataType::substringAfter($selector->toString(), $appAlias . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER);
            $roleSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE');
            $roleSheet->getColumns()->addFromUidAttribute();
            $roleSheet->getFilters()->addConditionFromString('ALIAS', $roleAlias);
            $roleSheet->getFilters()->addConditionFromString('APP__ALIAS', $appAlias);
            $roleSheet->dataRead();
            if ($roleSheet->countRows() === 1) {
                return $this->hasRole(new UserRoleSelector($this->getWorkbench(), $roleSheet->getUidColumn()->getCellValue(0)));
            }
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
        if (empty($this->roleSelectors) && $this->isAnonymous() === false) {
            $this->roleSelectors = $this->addBuiltInRoles($this->roleSelectors || []);
        }
        return $this->roleSelectors;
    }
    
    /**
     * 
     * @param UserRoleSelectorInterface[] $selectorArray
     * @return UserRoleSelectorInterface[]
     */
    protected function addBuiltInRoles(array $selectorArray) : array
    {
        $selectorArray[] = new UserRoleSelector($this->getWorkbench(), UserRoleSelector::AUTHENTICATED_USER_ROLE_OID);
        $selectorArray[] = new UserRoleSelector($this->getWorkbench(), UserRoleSelector::AUTHENTICATED_USER_ROLE_ALIAS);
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
}
