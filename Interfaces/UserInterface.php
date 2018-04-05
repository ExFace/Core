<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\Model\User;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface UserInterface extends WorkbenchDependantInterface
{

    /**
     * Returns the UID of the user.
     * 
     * @return string
     */
    public function getUid();

    /**
     * Returns the username of the user.
     * 
     * @return string
     */
    public function getUsername();

    /**
     * Sets the username of the user.
     * 
     * @param string $username
     * @return User
     */
    public function setUsername($username);

    /**
     * Returns the first name of the user.
     * 
     * @return string
     */
    public function getFirstName();

    /**
     * Sets the first name of the user.
     * 
     * @param string $firstname
     * @return User
     */
    public function setFirstName($firstname);

    /**
     * Returns the last name of the user.
     * 
     * @return string
     */
    public function getLastName();

    /**
     * Sets the last name of the user.
     * 
     * @param string $lastname
     * @return User
     */
    public function setLastName($lastname);

    /**
     * Returns the locale of the user (e.g. 'en_US').
     * 
     * @return string
     */
    public function getLocale();

    /**
     * Sets the locale of the user.
     * 
     * @param string $locale
     * @return User
     */
    public function setLocale($locale);

    /**
     * Returns the email of the user.
     * 
     * @return string
     */
    public function getEmail();

    /**
     * Sets the email of the user.
     * 
     * @param string $email
     * @return User
     */
    public function setEmail($email);

    /**
     * Returns a DataSheet representing all properties of the user.
     * 
     * @return DataSheetInterface
     */
    public function exportDataSheet();

    /**
     * Returns TRUE if the user currently logged in is an administrator and FALSE otherwise.
     *
     * @return boolean
     */
    public function isUserAdmin();

    /**
     * Returns TRUE if the user is anonymous and FALSE otherwise.
     * 
     * An anonymous user is returned if the currently logged in user is requested but no
     * named user is logged in.
     *
     * @return boolean
     */
    public function isUserAnonymous();
}