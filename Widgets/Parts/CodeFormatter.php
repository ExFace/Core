<?php

namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\InputCode;

/**
 * The CodeFormatter formats the value before it is displayed in an output.
 * 
 * ## Examples:
 * ```
 * "code_formatter": {
 *     "prettify": true,
 *     "colorize": true,
 *     "language": "sql",
 *     "dialect": "tsql"
 *  }
 * ```
 * 
 * @author Sergej Riel
 */
class CodeFormatter implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;

    const DIALECT_SQL = 'sql';
    const DIALECT_TSQL = 'tsql';
    const DIALECT_MARIADB = 'mariadb';
    const DIALECT_MYSQL = 'mysql';
    const DIALECT_ORACLE = 'plsql';
    const DIALECT_POSTGRESQL = 'postgresql';
    
    private $inputCode;
    private $language;
    private $dialect = null;
    private $prettify = true;
    private $colorize = true;
    
    public function __construct(InputCode $inputCode, ?UxonObject $uxon = null)
    {
        $this->inputCode = $inputCode;
        if ($uxon) {
            $this->importUxonObject($uxon);
        }
    }
    
    public function getWidget(): WidgetInterface
    {
        return $this->inputCode->getWidget();
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench(): Workbench
    {
        return $this->inputCode->getWorkbench();
    }

    /**
     * @param string|null $default
     * @return string|null
     */
    public function getLanguage(string $default = null): ?string
    {
        return $this->language;
    }
    
    /**
     * Sets the formatted language.
     * 
     * @uxon-property language
     * @uxon-type [sql,javascript,json]
     * 
     * @param string $language
     * @return $this
     */
    public function setLanguage(string $language) : CodeFormatter
    {
        $this->language = $language;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDialect() : ?string
    {
        return $this->dialect;
    }

    /**
     * Sets the dialect for formatted language. Example: language: "sql" and dialect: "tsql".
     * 
     * @uxon-property dialect
     * @uxon-type [sql,tsql,mariadb,mysql,plsql,postgresql]
     * 
     * @param string $dialect
     * @return $this
     */
    public function setDialect(string $dialect) : CodeFormatter
    {
        $this->dialect = $this->formatDialectName($dialect);
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getPrettify() : bool
    {
        return $this->prettify;
    }

    /**
     * Disables code formatting if set to false
     * 
     * @uxon-property prettify
     * @uxon-type boolean
     * @uxon-defaul true
     * 
     * @param bool $prettify
     * @return $this
     */
    public function setPrettify(bool $prettify) : CodeFormatter
    {
        $this->prettify = $prettify;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getColorize() : bool
    {
        return $this->colorize;
    }

    /**
     *  Disables code colouring if set to false
     * 
     * @uxon-property colorize
     * @uxon-type boolean
     * @uxon-defaul true
     * 
     * @param bool $colorize
     * @return $this
     */
    public function setColorize(bool $colorize) : CodeFormatter
    {
        $this->colorize = $colorize;
        return $this;
    }

    /**
     * This function maps internal dialect names to formatter-known names.
     * 
     * @param string $value
     * @return string
     */
    private function formatDialectName(string $value) : string
    {
        $value = mb_strtolower($value);
        
        switch ($value) {
            case 'mssql':
            case 't-sql': $value = self::DIALECT_TSQL; break;
            case 'mariadb':  $value = self::DIALECT_MARIADB; break;
            case 'mysql': $value = self::DIALECT_MYSQL; break;
            case 'oracle':
            case 'pl/sql': $value = self::DIALECT_ORACLE; break;
            case 'postgresql':
            case 'pgsql': $value = self::DIALECT_POSTGRESQL; break;
            case 'other':
            case 'sql' : $value = self::DIALECT_SQL; break;
        }
        
        return $value;
    }
}