<?php

namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\CodeFormatter;

/**
 * A code input that can show and format code inside the Ace editor.
 * 
 * ##Examples:
 * 
 * ```
 *  {
 *      "widget_type": "InputCode",
 *      "language": "sql",
 *      "editable": false,
 *      "width": "1000px",
 *      "height": "800px",
 *      "value": "SELECT * FROM myTable",
 *      "code_formatter": {
 *          "prettify": true,
 *          "colorize": true,
 *          "language": "sql",
 *          "dialect": "tsql"
 *       }
 *  }
 * ```
 * 
 * @author Sergej Riel
 */
class InputCode extends Input
{
    const LANGUAGE_SQL = 'sql';
    const LANGUAGE_JSON = 'json';
    const LANGUAGE_PHP = 'php';
    
    private $language = null;
    private $codeFormatter = null;
    private $editable = true;
    private ?UxonObject $codeFormatterUxon = null;
    
    
    /**
     * @param string|null $default
     * @return string|null
     */
    public function getLanguage(string $default = null) : ?string
    {
        return $this->language ?? $default;
    }

    /**
     * Sets the code language of the editor.
     * More languages are supported than are listed here.
     * 
     * @uxon-property language
     * @uxon-type [javascript,json,php,sql]
     * @uxon-defaul json
     * 
     * @param string $value
     * @return $this
     */
    public function setLanguage(string $value) : InputCode
    {
        $this->language = $value;
        return $this;
    }
    
    /**
     * It gets the code formatter.
     * 
     * @return CodeFormatter
     */
    public function getCodeFormatter(): CodeFormatter
    {
        if (!$this->hasCodeFormatter()) {
            if ($this->codeFormatterUxon === null) {
                $this->createDefaultCodeFormatter();
            } else {
                $this->codeFormatter = new CodeFormatter($this, $this->codeFormatterUxon);
            }
        }
        return $this->codeFormatter;
    }

    /**
     * The code formatter can format different code languages.
     * 
     * Use the 'dialect' property if the language has multiple dialects, 
     * such as 'SQL', which includes for example T-SQL and MySQL.
     * 
     * @uxon-property code_formatter
     * @uxon-type \exface\Core\Widgets\Parts\CodeFormatter
     * @uxon-template {"language": "sql", "dialect": "tsql"}
     * 
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setCodeFormatter(UxonObject $uxon) : InputCode
    {
        $this->codeFormatterUxon = $uxon;
        $this->codeFormatter = null;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEditable() : bool
    {
        return $this->editable;
    }

    /**
     * Sets the inputCode to editable.
     * The inputCode is editable by default.
     * 
     * @uxon-property editable
     * @uxon-type boolean
     * 
     * @param bool $editable
     * @return $this
     */
    public function setEditable(bool $editable) : InputCode
    {
        $this->editable = $editable;
        return $this;
    }

    /**
     * @return bool
     */
    protected function hasCodeFormatter() : bool
    {
        return $this->codeFormatter !== null;
    }

    /**
     * Creates default code formatter
     * 
     * @param $language
     * @return CodeFormatter
     */
    protected function createDefaultCodeFormatter($language) : CodeFormatter
    {
        return match ($language) {
            self::LANGUAGE_SQL => $this->createSqlCodeFormatter(),
            default => new CodeFormatter($this, new UxonObject([
                'language' => $language,
            ])),
        };
    }

    /**
     * Creates default sql formatter.
     * 
     * @return CodeFormatter
     */
    protected function createSqlCodeFormatter() : CodeFormatter
    {
        return new CodeFormatter($this, new UxonObject([
            'language' => 'sql',
            'dialect' => 'sql',
        ]));
    }
}