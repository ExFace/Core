<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;

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
    public function getUsername()
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::isUserAdmin()
     */
    public function isUserAdmin()
    {
        if ($this->isUserAnonymous()) {
            return false;
        }
        return $this->getWorkbench()->getCMS()->isUserAdmin();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::isUserAnonymous()
     */
    public function isUserAnonymous()
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
        if ($this->isUserAnonymous() === true) {
            return 'Guest';
        }
        return mb_substr($this->getFirstName(), 0, 1) . '.' . mb_substr($this->getLastName(), 0, 1) . '.';
    }
}
