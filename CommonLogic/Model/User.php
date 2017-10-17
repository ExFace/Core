<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\InvalidArgumentException;

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

    private $firstname;

    private $lastname;

    private $locale;

    private $email;

    private $anonymous = false;

    /**
     * 
     * @deprecated use UserFactory::create() instead!
     * @param Workbench $exface
     * @param DataSheetInterface $dataSheet
     * @param boolean $anonymous
     */
    public function __construct(Workbench $exface, $dataSheet = null, $anonymous = false)
    {
        $this->exface = $exface;
        
        if ($dataSheet) {
            $this->setDataSheet($dataSheet);
            if ($uid = $dataSheet->getCellValue($dataSheet->getUidColumnName(), 0)) {
                $this->uid = $uid;
            }
        }
        
        $this->anonymous = $anonymous;
    }

    /**
     * Returns the DataSheet the User is created from.
     * 
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    private function getDataSheet()
    {
        return $this->dataSheet;
    }

    /**
     * Saves the DataSheet the User is created from.
     * 
     * @param DataSheetInterface $dataSheet
     * @throws InvalidArgumentException
     * @return User
     */
    private function setDataSheet(DataSheetInterface $dataSheet)
    {
        if (! $dataSheet->getMetaObject()->isExactly('exface.Core.USER')) {
            throw new InvalidArgumentException('DataSheet with "' . $dataSheet->getMetaObject()->getAliasWithNamespace() . '" passed. Expected "exface.Core.USER".');
        }
        if ($dataSheet->countRows() != 1) {
            throw new InvalidArgumentException('DataSheet with ' . $dataSheet->countRows() . ' rows passed. Expected exactly one row.');
        }
        // Alle Filter werden entfernt (insbesondere ein moeglicher Filter nach dem Username. Das
        // ist wichtig beim Loeschen. Das Objekt axenox.TestMan.TEST_LOG enthaelt naemlich eine
        // Relation auf User, dadurch werden beim Loeschen auch Testlogs geloescht, welche der
        // Nutzer erstellt hat (Cascading Delete). Die Tabelle test_log befindet sich aber in
        // einer anderen Datenbank als exf_user, es kommt daher zu einem SQL-Error wenn versucht
        // wird die Uid aus dem Username zu ermitteln.
        $dataSheet->getFilters()->removeAll();
        $this->dataSheet = $dataSheet;
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::getUid()
     */
    public function getUid()
    {
        return $this->uid;
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
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
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
        
        if ($this->getDataSheet()) {
            $userSheet = $this->getDataSheet();
        } else {
            $userSheet = DataSheetFactory::createFromObject($userModel);
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
        
        return $userSheet;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::isUserAdmin()
     */
    public function isUserAdmin()
    {
        return $this->getWorkbench()->getCMS()->isUserAdmin();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UserInterface::isUserAnonymous()
     */
    public function isUserAnonymous()
    {
        return $this->anonymous;
    }
}
