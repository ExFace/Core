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

    public function initFromDb(array $data): SqlMigration
    {
        $this->id = $data['id'];
        $this->migration_name = !empty($data['migration_name']) ? $data['migration_name'] : '';
        $this->up_datetime = !empty($data['up_datetime']) ? $data['up_datetime'] : '';
        $this->up_script = !empty($data['up_script']) ? $data['up_script'] : '';
        $this->up_result = !empty($data['up_result']) ? $data['up_result'] : '';
        $this->down_datetime = !empty($data['down_datetime']) ? $data['down_datetime'] : '';
        $this->down_script = !empty($data['down_script']) ? $data['down_script'] : '';
        $this->down_result = !empty($data['down_result']) ? $data['down_result'] : '';
        $this->is_up =
            (!(bool)$data['down_datetime'] && !(bool)$data['failed_flag']) ||
            ((bool)$data['down_datetime'] && (bool)$data['failed_flag']);
        $this->failed_flag = (bool)$data['failed_flag'];
        $this->failed_message = !empty($data['failed_message']) ? $data['failed_message'] : '';
        $this->skip_flag = (bool)$data['skip_flag'];
        return $this;
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
     * Returns if the migration failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->failed_flag;
    }

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