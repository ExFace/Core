<?php
namespace exface\Core\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Factories\DataSheetFactory;

class UserContextScope extends AbstractContextScope
{

    private $user_data = null;

    private $user_locale = null;
    
    public function getScopeId(){
        return $this->getUserName();
    }

    public function getUserName()
    {
        return $this->getWorkbench()->getCMS()->getUserName();
    }

    public function getUserId()
    {
        return $this->getUserData()->getUidColumn()->getCellValue(0);
    }

    /**
     * Returns the absolute path to the base installation folder (e.g.
     * c:\xampp\htdocs\exface\exface\UserData\username)
     *
     * @return string
     */
    public function getUserDataFolderAbsolutePath()
    {
        $path = $this->getWorkbench()->filemanager()->getPathToUserDataFolder() . DIRECTORY_SEPARATOR . $this->getUserDataFolderName();
        if (! file_exists($path)) {
            mkdir($path);
        }
        return $path;
    }

    public function getUserDataFolderName()
    {
        return $this->getUserName() ? $this->getUserName() : '.anonymous';
    }

    /**
     * TODO
     *
     * @see \exface\Core\Contexts\Scopes\AbstractContextScope::loadContextData()
     */
    public function loadContextData(ContextInterface $context)
    {}

    public function saveContexts()
    {}

    /**
     * Returns a data sheet with all data from the user object
     *
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    protected function getUserData()
    {
        if (is_null($this->user_data)) {
            $user_object = $this->getWorkbench()->model()->getObject('exface.Core.USER');
            $ds = DataSheetFactory::createFromObject($user_object);
            $ds->getColumns()->addFromExpression($user_object->getUidAlias());
            $ds->getColumns()->addFromExpression('USERNAME');
            $ds->getColumns()->addFromExpression('FIRST_NAME');
            $ds->getColumns()->addFromExpression('LAST_NAME');
            $ds->addFilterFromString('USERNAME', $this->getUserName());
            $ds->dataRead();
            $this->user_data = $ds;
        }
        return $this->user_data;
    }

    /**
     * Returns the locale, set for the current user
     *
     * @return string
     */
    public function getUserLocale()
    {
        if (is_null($this->user_locale) && $cms_locale = $this->getWorkbench()->getCMS()->getUserLocale()) {
            $this->setUserLocale($cms_locale);
        }
        return $this->user_locale;
    }

    /**
     * Sets the locale for the current user
     *
     * @param string $string            
     * @return \exface\Core\Contexts\Scopes\UserContextScope
     */
    public function setUserLocale($string)
    {
        $this->user_locale = $string;
        return $this;
    }
}
?>