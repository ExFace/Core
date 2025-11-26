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
     * @uxon-type [sql, json]
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
     * @uxon-type [sql,tsql mariadb,mysql,plsql,postgresql]
     * 
     * @param string $dialect
     * @return $this
     */
    public function setDialect(string $dialect) : CodeFormatter
    {
        $this->dialect = $dialect;
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
}