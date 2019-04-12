<?php

namespace exface\Core\CommonLogic\AppInstallers;

/**
 * AppInstaller for Apps with MySQL Database.
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
    
    public function __construct($migration_name, $up_script, $down_script)
    {
        $this->migration_name=$migration_name;
        $this->up_script=$up_script;
        $this->down_script=$down_script;
    }
    
    public function setId($id)
    {
        $this->id=$id;
        return $this;
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function getMigrationName()
    {
        return $this->migration_name;
    }
    
    public function setUpDatetime($up_datetime)
    {
        $this->up_datetime = $up_datetime;
        return $this;
    }
    
    public function getUpDatetime()
    {
        return $this->up_datetime;
    }
    
    public function getUpScript()
    {
        return $this->up_script;
    }
    
    public function setUpResult($up_result)
    {
        $this->up_result = $up_result;
        return $this;
    }
    
    public function getUpResult()
    {
        return $this->up_result;
    }
    
    public function setDownDatetime($down_datetime)
    {
        $this->down_datetime = $down_datetime;
        return $this;
    }
    
    public function getDownDatetime()
    {
        return $this->down_datetime;
    }
    
    public function getDownScript()
    {
        return $this->down_script;
    }
    
    public function setDownResult($down_result)
    {
        $this->down_result = $down_result;
        return $this;
    }
    
    public function getDownResult()
    {
        return $this->down_result;
    }
    
    public function isUp(): bool
    {
        if ($this->up_datetime === null){
            return FALSE;
        } else {
            return TRUE;
        }
    }
    
    public function isDown(): bool
    {
        if ($this->down_datetime === null){
            return FALSE;
        } else {
            return TRUE;
        }
    }
    
    public function equals(SqlMigration $otherMigration) : bool
    {
        return $this->getMigrationName() === $otherMigration->getMigrationName();
    }
}