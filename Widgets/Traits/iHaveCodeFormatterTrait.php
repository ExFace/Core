<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\InputCode;
use exface\Core\Widgets\Parts\CodeFormatter;

/**
 * Shares library-independent code presentation settings between code inputs and Markdown widgets.
 */
trait iHaveCodeFormatterTrait
{
    private ?CodeFormatter $codeFormatter = null;
    private ?UxonObject $codeFormatterUxon = null;

    /**
     * Resolves presentation settings lazily so widget language inference has finished first.
     *
     * @return CodeFormatter
     */
    public function getCodeFormatter() : CodeFormatter
    {
        if (!$this->hasCodeFormatter()) {
            if ($this->codeFormatterUxon === null) {
                $language = $this instanceof InputCode ? $this->getLanguage() : null;
                $this->codeFormatter = $this->createDefaultCodeFormatter($language);
            } else {
                $this->codeFormatter = new CodeFormatter($this, $this->codeFormatterUxon);
            }
        }
        return $this->codeFormatter;
    }

    /**
     * Configure code presentation using language, colorize, prettify and dialect settings.
     * 
    * In Markdown widgets, omit language to use each code block's language. A configured
     * language limits highlighting to that language. Prettify and dialect apply only
     * to code inputs; Markdown code block source is never automatically reformatted.
     * 
     * @uxon-property code_formatter
     * @uxon-type \exface\Core\Widgets\Parts\CodeFormatter
     * @uxon-template {"language": "sql", "dialect": "tsql"}
     * 
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setCodeFormatter(UxonObject $uxon) : static
    {
        $this->codeFormatterUxon = $uxon;
        $this->codeFormatter = null;
        return $this;
    }

    /**
     * Allows consumers to retain a single formatter instance per widget configuration.
     *
     * @return bool
     */
    protected function hasCodeFormatter() : bool
    {
        return $this->codeFormatter !== null;
    }

    /**
     * Preserves SQL defaults while allowing Markdown blocks to choose their own language.
     *
     * @param string|null $language
     * @return CodeFormatter
     */
    protected function createDefaultCodeFormatter($language) : CodeFormatter
    {
        return $language === InputCode::LANGUAGE_SQL
            ? $this->createSqlCodeFormatter()
            : new CodeFormatter($this, $language === null ? null : new UxonObject(['language' => $language]));
    }

    /**
     * Retains the default SQL dialect expected by existing code inputs.
     *
     * @return CodeFormatter
     */
    protected function createSqlCodeFormatter() : CodeFormatter
    {
        return new CodeFormatter($this, new UxonObject(['language' => 'sql', 'dialect' => 'sql']));
    }
}