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

    private $failed_flag = false;

    private $failed_message = '';

    private $skip_flag = false;


    /**
     *
     * @param string $migration_name
     * @param string $up_script
     * @param string $down_script
     */
    public function __construct($migration_name, $up_script, $down_script)
    {
        $this->migration_name = $migration_name;
        $this->up_script = $up_script;
        $this->down_script = $down_script;
    }

    /**
     * Init the SqlMigration from a database array containing the necessary data.
     *
     * @param array $row
     * @return SqlMigration
     */
    public static function constructFromDb(array $row): SqlMigration
    {
        $instance = new self($row['migration_name'], $row['up_script'], $row['down_script']);
        $instance->id = $row['id'];
        $instance->migration_name = !empty($row['migration_name']) ? $row['migration_name'] : '';
        $instance->up_datetime = !empty($row['up_datetime']) ? $row['up_datetime'] : '';
        $instance->up_script = !empty($row['up_script']) ? $row['up_script'] : '';
        $instance->up_result = !empty($row['up_result']) ? $row['up_result'] : '';
        $instance->down_datetime = !empty($row['down_datetime']) ? $row['down_datetime'] : '';
        $instance->down_script = !empty($row['down_script']) ? $row['down_script'] : '';
        $instance->down_result = !empty($row['down_result']) ? $row['down_result'] : '';
        $instance->is_up =
            (!(bool)$row['down_datetime'] && !(bool)$row['failed_flag']) ||
            ((bool)$row['down_datetime'] && (bool)$row['failed_flag']);
        $instance->failed_flag = (bool)$row['failed_flag'];
        $instance->failed_message = !empty($row['failed_message']) ? $row['failed_message'] : '';
        $instance->skip_flag = (bool)$row['skip_flag'];
        return $instance;
    }

    /**
     * Returns id of the SqlMigration
     *
     * @return string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Sets the id of the SqlMigration
     *
     * @param string $id
     * @return SqlMigration
     */
    public function setId(string $id): SqlMigration
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Returns name of the SqlMigration
     *
     * @return string
     */
    public function getMigrationName(): string
    {
        return $this->migration_name;
    }

    /**
     * Returns Up Datetime of the SqlMigration
     *
     * @return string
     */
    public function getUpDatetime(): string
    {
        return $this->up_datetime;
    }

    /**
     * Returns Up script of the SqlMigration
     *
     * @return string
     */
    public function getUpScript(): string
    {
        return $this->up_script;
    }

    /**
     * Sets the UP-script of the SqlMigration
     *
     * @param string $upScript
     * @return SqlMigration
     */
    public function setUpScript(string $upScript): SqlMigration
    {
        $this->up_script = $upScript;
        return $this;
    }

    /**
     * Returns up result of the SqlMigration
     *
     * @return string
     */
    public function getUpResult(): string
    {
        return $this->up_result;
    }
    
    /**
     * Returns Down datetime of SqlMigration
     *
     * @return string
     */
    public function getDownDatetime() : string
    {
        return $this->down_datetime;
    }

    /**
     * Returns Down script of SqlMigration
     *
     * @return string
     */
    public function getDownScript(): string
    {
        return $this->down_script;
    }

    /**
     * Sets the DOWN-script of the SqlMigration
     *
     * @param string $downScript
     * @return SqlMigration
     */
    public function setDownScript(string $downScript): SqlMigration
    {
        $this->down_script = $downScript;
        return $this;
    }

    /**
     * Returns Down result of SqlMigration
     *
     * @return string
     */
    public function getDownResult(): string
    {
        return $this->down_result;
    }

    /**
     * Returns if Migration is up (TRUE) or not (FALSE)
     *
     * @return bool
     */
    public function isUp(): bool
    {
        return $this->is_up;
    }
    
    /**
     * Returns TRUE if migration was teared down and FALSE otherwise
     * 
     * @return bool
     */
    public function isDown() : bool
    {
        return empty($this->getDownDatetime()) === false;
    }

    /**
     * Returns if the migration failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->failed_flag;
    }

    /**
     * Sets the failed flag of the SqlMigration
     *
     * @param bool $failed
     * @return SqlMigration
     */
    public function setFailed(bool $failed): SqlMigration
    {
        $this->failed_flag = $failed;
        return $this;
    }

    /**
     * Returns the error message with which the migration failed.
     *
     * @return string
     */
    public function getFailedMessage(): string
    {
        return $this->failed_message;
    }

    /**
     * Sets the failed message of the SqlMigration
     *
     * @param string $failed_message
     * @return SqlMigration
     */
    public function setFailedMessage(string $failed_message): SqlMigration
    {
        $this->failed_message = $failed_message;
        return $this;
    }

    /**
     * Returns if the migration is skipped.
     *
     * @return bool
     */
    public function isSkipped(): bool
    {
        return $this->skip_flag;
    }

    /**
     * Sets the skip flag of the SqlMigration
     *
     * @param bool $skipped
     * @return SqlMigration
     */
    public function setSkipped(bool $skipped): SqlMigration
    {
        $this->skip_flag = $skipped;
        return $this;
    }

    /**
     * Function to compare if this migration equals other Migration.
     * They are equal when the Migration name is the same.
     *
     * @param SqlMigration $otherMigration
     * @return bool
     */
    public function equals(SqlMigration $otherMigration): bool
    {
        $thisFileName = pathinfo($this->getMigrationName(), PATHINFO_FILENAME);
        $otherFileName = pathinfo($otherMigration->getMigrationName(), PATHINFO_FILENAME);
        return $thisFileName === $otherFileName;
    }
    
    /**
     * Function to change the state of the migration to UP, meaning $is_up = TRUE.
     *
     * @param string $dateTime
     * @param string $result
     * @return SqlMigration
     */
    public function setUp(string $dateTime, string $result): SqlMigration
    {
        $this->is_up = true;
        $this->up_datetime = $dateTime;
        $this->up_result = $result;
        return $this;
    }
    
    /***
     * Function to change the state of migration to DOWN, meaning $is_up = FALSE.
     * 
     * @param string $dateTime
     * @param string $result
     * @return SqlMigration
     */
    public function setDown(string $dateTime, string $result): SqlMigration
    {
        $this->is_up = false;
        $this->down_datetime = $dateTime;
        $this->down_result = $result;
        return $this;
    }
}
?>