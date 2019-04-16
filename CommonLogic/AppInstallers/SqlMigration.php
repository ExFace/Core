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
    
    private $up_datetime = '';
    
    private $up_script = '';
    
    private $up_result = '';
    
    private $down_datetime = '';
    
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
     * @return SqlMigration
     */
    public function setId(string $id) : SqlMigration
    {
        $this->id=$id;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getId() : string
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
     * @return SqlMigration
     */
    public function setUpDatetime(string $up_datetime) : SqlMigration
    {
        $this->up_datetime = $up_datetime;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getUpDatetime() : string
    {
        return $this->up_datetime;
    }
    
    /**
     *
     * @return string
     */
    public function getUpScript() : string
    {
        return $this->up_script;
    }
    
    /**
     * 
     * @param string $up_result
     * @return SqlMigration
     */
    public function setUpResult(string $up_result) : SqlMigration
    {
        $this->up_result = $up_result;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getUpResult() : string
    {
        return $this->up_result;
    }
    
    /**
     * 
     * @param string $down_datetime
     * @return SqlMigration
     */
    public function setDownDatetime(string $down_datetime) : SqlMigration
    {
        $this->down_datetime = $down_datetime;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getDownDatetime() : string
    {
        return $this->down_datetime;
    }
    
    /**
     *
     * @return string
     */
    public function getDownScript() : string
    {
        return $this->down_script;
    }
    
    /**
     * 
     * @param string $down_result
     * @return SqlMigration
     */
    public function setDownResult(string $down_result) : SqlMigration
    {
        $this->down_result = $down_result;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getDownResult() : string
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
    public function setIsUp(bool $bool) : SqlMigration
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