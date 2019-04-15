<?php

namespace exface\Core\CommonLogic\AppInstallers;

/**
 * Class for SQL Database Migrations.
 *
 * @author Ralf Mulansky
 *
 */

class SqlMigration
{
    private $id = null;
    
    private $migration_name= '';
    
    private $up_datetime = null;
    
    private $up_script = '';
    
    private $up_result = '';
    
    private $down_datetime = null;
    
    private $down_script = '';
    
    private $down_result = '';
    
    private $is_up = FALSE;
    
    
    /**
     * 
     * @param string $migration_name
     * @param string $up_script
     * @param string $down_script
     */
    public function __construct($migration_name, $up_script, $down_script)
    {
        $this->migration_name=$migration_name;
        $this->up_script=$up_script;
        $this->down_script=$down_script;
    }
    
    /**
     * 
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id=$id;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     *
     * @return string
     */
    public function getMigrationName() : string
    {
        return $this->migration_name;
    }
    
    /**
     *
     * @param string $up_datetime
     * @return $this
     */
    public function setUpDatetime($up_datetime)
    {
        $this->up_datetime = $up_datetime;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getUpDatetime()
    {
        return $this->up_datetime;
    }
    
    /**
     *
     * @return string
     */
    public function getUpScript()
    {
        return $this->up_script;
    }
    
    /**
     *
     * @param string $up_result
     * @return $this
     */
    public function setUpResult($up_result)
    {
        $this->up_result = $up_result;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getUpResult()
    {
        return $this->up_result;
    }
    
    /**
     *
     * @param string $down_datetime
     * @return $this
     */
    public function setDownDatetime($down_datetime)
    {
        $this->down_datetime = $down_datetime;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getDownDatetime()
    {
        return $this->down_datetime;
    }
    
    /**
     *
     * @return string
     */
    public function getDownScript()
    {
        return $this->down_script;
    }
    
    /**
     *
     * @param string $down_result
     * @return $this
     */
    public function setDownResult($down_result)
    {
        $this->down_result = $down_result;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getDownResult()
    {
        return $this->down_result;
    }
    
    /**
     *
     * @return bool
     */
    public function getIsUp(): bool
    {
        return $this->is_up;
    }
    
    /**
     * 
     * @param bool $bool
     * @return SqlMigration
     */
    public function setIsUp(bool $bool)
    {
        $this->is_up=$bool;
        return $this;
    }
    
    /**
     * 
     * @param SqlMigration $otherMigration
     * @return bool
     */
    public function equals(SqlMigration $otherMigration) : bool
    {
        return $this->getMigrationName() === $otherMigration->getMigrationName();
    }
}
?>