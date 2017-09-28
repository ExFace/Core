<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\RuntimeException;

class ExfaceUserFunctions
{

    /**
     * Creates an exface user.
     * 
     * @param string $username
     * @param string $firstname
     * @param string $lastname
     * @param string $locale
     * @return DataSheetInterface
     */
    public static function exfaceUserCreate($username, $firstname, $lastname, $locale)
    {
        global $exface;
        
        $user = $exface->model()->getObject('exface.Core.USER');
        $exf_user = DataSheetFactory::createFromObject($user);
        $exf_user->getColumns()->addFromAttribute($user->getAttribute('USERNAME'));
        $exf_user->getColumns()->addFromAttribute($user->getAttribute('FIRST_NAME'));
        $exf_user->getColumns()->addFromAttribute($user->getAttribute('LAST_NAME'));
        $exf_user->getColumns()->addFromAttribute($user->getAttribute('LOCALE'));
        $exf_user->addRow([
            'USERNAME' => $username,
            'FIRST_NAME' => $firstname,
            'LAST_NAME' => $lastname,
            'LOCALE' => $locale
        ]);
        $exf_user->dataCreate();
        
        return $exf_user;
    }

    /**
     * Reads an exface user.
     * 
     * @param string $username
     * @throws RuntimeException
     * @return DataSheetInterface|null
     */
    public static function exfaceUserRead($username)
    {
        global $exface;
        
        $user = $exface->model()->getObject('exface.Core.USER');
        $exf_user = DataSheetFactory::createFromObject($user);
        foreach ($user->getAttributes() as $attr) {
            $exf_user->getColumns()->addFromAttribute($attr);
        }
        $exf_user->getFilters()->addConditionsFromString($user, 'USERNAME', $username, EXF_COMPARATOR_EQUALS);
        $exf_user->dataRead();
        // Der Filter nach dem Username wird entfernt. Das ist wichtig fuer das Loeschen. Das
        // Objekt axenox.TestMan.TEST_LOG enthaelt naemlich eine Relation auf User, dadurch
        // werden beim Loeschen auch Testlogs geloescht, welche der Nutzer erstellt hat
        // (Cascading Delete). Die Tabelle test_log befindet sich aber in einer anderen
        // Datenbank als exf_user, es kommt daher zu einem SQL-Error wenn versucht wird die Uid
        // aus dem Username zu ermitteln.
        $exf_user->getFilters()->removeAll();
        
        if ($exf_user->countRows() == 0) {
            return null;
        } elseif ($exf_user->countRows() == 1) {
            return $exf_user;
        } else {
            throw new RuntimeException('More than one Exface users with username "' . $username . '" defined.');
        }
    }

    /**
     * Updates an exface user. If a DataSheet is passed it is used, otherwise the user is read
     * from the database using the passed $oldusername.
     * 
     * @param string $oldusername
     * @param string $username
     * @param string $firstname
     * @param string $lastname
     * @param string $locale
     * @param DataSheetInterface $exf_user
     * @return DataSheetInterface
     */
    public static function exfaceUserUpdate($oldusername, $username, $firstname, $lastname, $locale, $exf_user = null)
    {
        if (! $exf_user) {
            $exf_user = static::exfaceUserRead($oldusername);
            if ($exf_user->countRows() == 0) {
                throw new RuntimeException('No Exface user with username "' . $oldusername . '" defined.');
            }
        }
        $exf_user->setCellValue('USERNAME', 0, $username);
        // Wichtig, da der Username auch das Label ist.
        $exf_user->setCellValue('LABEL', 0, $username);
        $exf_user->setCellValue('FIRST_NAME', 0, $firstname);
        $exf_user->setCellValue('LAST_NAME', 0, $lastname);
        $exf_user->setCellValue('LOCALE', 0, $locale);
        $exf_user->dataUpdate();
        
        return $exf_user;
    }

    /**
     * Deletes an exface user. If a DataSheet is passed it is used, otherwise the user is read
     * from the database using the passed $username.
     * 
     * @param string $username
     * @param DataSheetInterface $exf_user
     * @return DataSheetInterface|null
     */
    public static function exfaceUserDelete($username, $exf_user = null)
    {
        if (! $exf_user) {
            $exf_user = static::exfaceUserRead($username);
        }
        if ($exf_user) {
            $exf_user->dataDelete();
        }
        
        return $exf_user;
    }
}
