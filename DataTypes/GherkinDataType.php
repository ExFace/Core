<?php
namespace exface\Core\DataTypes;

/**
 * Data type for Gherkin source code (Cucumber / Behat `.feature` files) with structural validation.
 *
 * Any attribute holding raw Gherkin text can use this data type to get syntax highlighting in
 * code editors (via `CodeDataType::getLanguage()`) and - more importantly - an automatic
 * structural validation whenever a value is parsed by the metamodel.
 *
 * The validation catches problems that would make the Gherkin parser fail at parse/bootstrap
 * time - that is errors which prevent ANY scenario from running, not just the broken one. These
 * are the errors that must block a save operation, so a single bad edit cannot break an entire
 * nightly test suite.
 *
 * Problems that are NOT fatal for the parser (undefined steps, missing scenario titles, etc.)
 * are intentionally ignored: they produce warnings at runtime, but they do not stop other
 * features from executing and must not prevent a user from saving work in progress.
 *
 * ## Usage in the metamodel
 *
 * ```
 * {
 *      "strict": true
 * }
 * ```
 *
 * ## Usage in PHP
 *
 * ```
 * $errors = GherkinDataType::findErrors($featureFileContent);
 * if ($errors !== []) {
 *      throw new RuntimeException('Invalid feature file: ' . GherkinDataType::formatErrors($errors));
 * }
 * ```
 *
 * The static API is deliberately independent of the workbench, so apps can validate Gherkin
 * without instantiating the data type (e.g. inside behaviors, CLI actions or importers).
 *
 * @author Andrej Kabachnik
 */
class GherkinDataType extends CodeDataType
{
    /**
     * Language identifier for code editors - see the `language` property of CodeDataType
     */
    const LANGUAGE_GHERKIN = 'gherkin';

    /**
     * Gherkin keywords that must start every step line
     */
    private const STEP_KEYWORDS = ['Given', 'When', 'Then', 'And', 'But', '*'];

    /**
     * @var bool|null NULL means "not set explicitly" - see isStrict()
     */
    private ?bool $strict = null;

    /**
     * Validates the Gherkin structure in addition to the regular string parsing.
     *
     * This is the hook that makes the validation happen automatically everywhere in the
     * metamodel - data sheets, widgets, actions and imports all run values through parse().
     * Without this override the checks below would only be available to code that calls them
     * explicitly.
     *
     * Empty values are accepted on purpose: whether a value is required at all is a matter of
     * the attribute (required-flag), not of the data type.
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::parse()
     */
    public function parse($string)
    {
        $value = parent::parse($string);

        if ($value === null || trim($value) === '') {
            return $value;
        }

        $errors = self::findErrors($value, $this->isStrict());
        if ($errors !== []) {
            // Never put excerpts of the value itself into the message if the data is marked
            // sensitive - the error texts below quote parts of the offending lines.
            if ($this->isSensitiveData()) {
                $message = 'Invalid Gherkin syntax: ' . count($errors) . ' structural error(s) found!';
            } else {
                $message = 'Invalid Gherkin syntax:' . PHP_EOL . self::formatErrors($errors);
            }
            throw $this->createValidationRuleError($value, $message, true);
        }

        return $value;
    }

    /**
     * Returns `gherkin` unless another language was configured explicitly.
     *
     * Code editors in the facades pick their highlighting mode from this value. Since this data
     * type exists specifically for Gherkin, the default of `CodeDataType` (no language) would
     * force every model author to set the property manually.
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\CodeDataType::getLanguage()
     */
    public function getLanguage(): ?string
    {
        return parent::getLanguage() ?? self::LANGUAGE_GHERKIN;
    }

    /**
     * Returns TRUE if convention checks are performed in addition to the fatal parser checks.
     *
     * Kept separate from the property itself, so that "not configured" can default to TRUE
     * without writing the default into every exported UXON.
     *
     * @return bool
     */
    public function isStrict() : bool
    {
        return $this->strict ?? true;
    }

    /**
     * Set to FALSE to only report errors that actually break the Gherkin parser.
     *
     * Strict mode (default) additionally reports issues that the parser tolerates, but that
     * almost always indicate a mistake: ragged data tables, duplicate tags, inline comments
     * behind table rows and unclosed quotes inside table cells.
     *
     * Apps that import feature files from external sources may need to switch this off in
     * order to be able to save files they do not control.
     *
     * @uxon-property strict
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return GherkinDataType
     */
    public function setStrict(bool $value) : GherkinDataType
    {
        $this->strict = $value;
        return $this;
    }

    /**
     * Exports the `strict` property only if it was set explicitly.
     *
     * Required because the UXON of a data type is regenerated when models are exported - an
     * unset property must not appear there as a hard-coded default.
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if ($this->strict !== null) {
            $uxon->setProperty('strict', $this->strict);
        }
        return $uxon;
    }

    /**
     * Validates raw Gherkin content and returns all errors found as an array of texts.
     *
     * Static and workbench-independent on purpose: behaviors, actions and other apps need to
     * validate content without having a data type instance at hand. All checks are executed in
     * sequence and every error is collected, so the user sees all problems at once instead of
     * fixing them one by one over multiple save attempts.
     *
     * @param string $gherkin Raw text content of a .feature file
     * @param bool $strict Set to FALSE to skip checks that the parser tolerates
     * @return string[]
     */
    public static function findErrors(string $gherkin, bool $strict = true) : array
    {
        // Use the Core line splitter - it handles \r\n, \r and \n, so line numbers in the error
        // messages are correct regardless of where the file was edited.
        $lines = StringDataType::splitLines($gherkin);

        // Errors that make the Gherkin parser abort - these break the whole suite.
        $errors = array_merge(
            self::checkFeatureKeyword($lines),
            self::checkTagSyntax($lines),
            self::checkStepKeywords($lines),
            self::checkScenarioKeywords($lines),
            self::checkDocStringClosure($lines),
            self::checkExamplesTableConsistency($lines),
            self::checkExamplesRequireOutline($lines),
            self::checkStepsAfterExamples($lines),
            self::checkTableRowClosingPipe($lines)
        );

        // Issues the parser tolerates, but that always indicate a mistake.
        if ($strict === true) {
            $errors = array_merge(
                $errors,
                self::checkDataTableAlignment($lines),
                self::checkExamplesInlineComments($lines),
                self::checkUnclosedQuotesInTableCells($lines),
                self::checkDuplicateTags($lines)
            );
        }

        return self::sortErrorsByLine($errors);
    }

    /**
     * Returns TRUE if the given content contains no errors.
     *
     * Convenience wrapper for callers that only need a yes/no answer - e.g. UI code that greys
     * out a "run" button.
     *
     * @param string $gherkin
     * @param bool $strict
     * @return bool
     */
    public static function isValidGherkin(string $gherkin, bool $strict = true) : bool
    {
        return self::findErrors($gherkin, $strict) === [];
    }

    /**
     * Formats a list of error texts into a numbered, human readable block.
     *
     * Centralized here so that every caller (data type validation, behaviors, CLI output)
     * presents the same errors in the same way.
     *
     * @param string[] $errors
     * @return string
     */
    public static function formatErrors(array $errors) : string
    {
        $lines = [];
        foreach (array_values($errors) as $i => $error) {
            $lines[] = ($i + 1) . '. ' . $error;
        }
        return implode(PHP_EOL, $lines);
    }

    /**
     * Sorts error messages by the line number mentioned at their beginning.
     *
     * The checks run one after another, so without sorting the user gets the errors grouped by
     * check instead of in the order they appear in the file - which makes fixing them
     * unnecessarily hard. Messages without a line number (e.g. "file is empty") keep their
     * position at the top.
     *
     * @param string[] $errors
     * @return string[]
     */
    private static function sortErrorsByLine(array $errors) : array
    {
        usort($errors, function(string $a, string $b) {
            return self::extractLineNo($a) <=> self::extractLineNo($b);
        });
        return $errors;
    }

    /**
     * Reads the leading "Line N:" prefix of an error message.
     *
     * Needed by the sorting above. Returns 0 for messages without a prefix, so they are listed
     * first - they concern the file as a whole.
     *
     * @param string $error
     * @return int
     */
    private static function extractLineNo(string $error) : int
    {
        $matches = [];
        if (preg_match('/^Line (\d+):/', $error, $matches) === 1) {
            return (int) $matches[1];
        }
        return 0;
    }

    /**
     * Ensures the file starts with a "Feature:" keyword.
     *
     * Gherkin requires every feature file to declare a Feature block. Without it the parser
     * throws a fatal parse error and the whole suite cannot start.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkFeatureKeyword(array $lines) : array
    {
        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Tags are allowed above the Feature keyword - everything else is not.
            if ($trimmed === '' || self::startsWith($trimmed, '#') || self::startsWith($trimmed, '@')) {
                continue;
            }
            if (! self::startsWith($trimmed, 'Feature:')) {
                return ['Line 1: Feature file must start with "Feature:" keyword (got: "' . mb_substr($trimmed, 0, 40) . '")'];
            }
            return [];
        }
        return ['File is empty or contains only comments - "Feature:" keyword is missing.'];
    }

    /**
     * Validates that all tag lines use correct Gherkin tag syntax.
     *
     * A token without "@" on a tag line is parsed as a separate token and silently ignored by
     * the tag filter, so a scenario would not be picked up by the tag it appears to have.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkTagSyntax(array $lines) : array
    {
        $errors = [];
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || ! self::startsWith($trimmed, '@')) {
                continue;
            }

            // Strip a trailing comment - it is not part of the tag list.
            $withoutComment = preg_replace('/#.*$/', '', $trimmed);
            $tokens = preg_split('/\s+/u', trim($withoutComment));

            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }
                if (! self::startsWith($token, '@')) {
                    $errors[] = 'Line ' . ($i + 1) . ': Non-tag token "' . $token . '" found on a tag line. '
                        . 'Each token on a tag line must start with "@".';
                    continue;
                }
                // Unicode spaces (e.g. non-breaking space pasted from a document) are not
                // matched by the split above, but they do break the tag at runtime.
                if (preg_match('/[\p{Z}\x{00A0}]/u', $token) === 1) {
                    $errors[] = 'Line ' . ($i + 1) . ': Tag "' . $token . '" contains whitespace, which is not allowed.';
                }
                // Tags with values use "::" as separator - no spaces allowed around it.
                if (str_contains($token, '::')) {
                    [$tagName, $tagValue] = explode('::', $token, 2);
                    if (trim($tagName) !== $tagName || trim($tagValue) !== $tagValue) {
                        $errors[] = 'Line ' . ($i + 1) . ': Tag "' . $token . '" has unexpected spaces around "::".';
                    }
                }
            }
        }
        return $errors;
    }

    /**
     * Checks that every step line begins with a recognized Gherkin step keyword.
     *
     * A step that starts with a random word (e.g. "I click the button" instead of "When I click
     * the button") causes a parse error that aborts the entire feature file.
     *
     * Lines inside tables and DocStrings are excluded - they are step arguments, not steps.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkStepKeywords(array $lines) : array
    {
        $errors      = [];
        $inDocString = false;
        $inScenario  = false;
        $inExamples  = false;

        $scenarioKeywords = ['Scenario Outline:', 'Scenario Template:', 'Scenario:', 'Background:'];
        $blockKeywords    = ['Feature:', 'Background:', 'Scenario:', 'Scenario Outline:', 'Scenario Template:', 'Examples:', 'Rule:'];

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            // DocStrings (""") contain free text - nothing inside is parsed as Gherkin.
            if (self::startsWith($trimmed, '"""')) {
                $inDocString = ! $inDocString;
                continue;
            }
            if ($inDocString) {
                continue;
            }
            if ($trimmed === '' || self::startsWith($trimmed, '#')) {
                continue;
            }

            // Entering a scenario block - from here on, lines are expected to be steps.
            foreach ($scenarioKeywords as $kw) {
                if (self::startsWith($trimmed, $kw)) {
                    $inScenario = true;
                    $inExamples = false;
                    continue 2;
                }
            }
            if (self::startsWith($trimmed, 'Examples:')) {
                $inExamples = true;
                continue;
            }
            // Table rows and tags are not steps.
            if (self::startsWith($trimmed, '|') || self::startsWith($trimmed, '@')) {
                continue;
            }
            // Any other block keyword closes the scenario context.
            $isBlockKeyword = false;
            foreach ($blockKeywords as $kw) {
                if (self::startsWith($trimmed, $kw)) {
                    $isBlockKeyword = true;
                    break;
                }
            }
            if ($isBlockKeyword) {
                $inScenario = false;
                $inExamples = false;
                continue;
            }

            if (! $inScenario || $inExamples) {
                continue;
            }

            // This line must be a step - verify its keyword.
            $startsWithKeyword = false;
            foreach (self::STEP_KEYWORDS as $kw) {
                if (self::startsWith($trimmed, $kw . ' ') || $trimmed === $kw) {
                    $startsWithKeyword = true;
                    break;
                }
            }
            if (! $startsWithKeyword) {
                $errors[] = 'Line ' . ($i + 1) . ': Step line does not start with a Gherkin keyword '
                    . '(Given/When/Then/And/But). Got: "' . mb_substr($trimmed, 0, 60) . '"';
            }
        }
        return $errors;
    }

    /**
     * Verifies that every Examples table has the same number of columns in the header row and
     * in all data rows.
     *
     * A column count mismatch is a fatal parse error: Behat cannot build the outline examples
     * and refuses to run the entire feature file.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkExamplesTableConsistency(array $lines) : array
    {
        $errors       = [];
        $inDocString  = false;
        $inExamples   = false;
        $headerCols   = null;
        $headerLineNo = null;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            // A DocString may contain pipes or the word "Examples:" as plain text.
            if (self::startsWith($trimmed, '"""')) {
                $inDocString = ! $inDocString;
                continue;
            }
            if ($inDocString) {
                continue;
            }

            if (self::startsWith($trimmed, 'Examples:')) {
                $inExamples   = true;
                $headerCols   = null;
                $headerLineNo = null;
                continue;
            }

            // A comment line inside a table does not end the table.
            if ($inExamples && self::startsWith($trimmed, '#')) {
                continue;
            }

            // Any non-table, non-empty, non-comment line ends the Examples block.
            if ($inExamples && ! self::startsWith($trimmed, '|') && $trimmed !== '') {
                $inExamples = false;
                $headerCols = null;
                continue;
            }

            if ($inExamples && self::startsWith($trimmed, '|')) {
                $cols = self::countTableColumns($trimmed);
                if ($headerCols === null) {
                    $headerCols   = $cols;
                    $headerLineNo = $i + 1;
                } elseif ($cols !== $headerCols) {
                    $errors[] = 'Line ' . ($i + 1) . ': Examples table row has ' . $cols
                        . ' column(s) but the header on line ' . $headerLineNo
                        . ' has ' . $headerCols . ' column(s).';
                }
            }
        }
        return $errors;
    }

    /**
     * Detects inline comments behind the closing pipe of a table row.
     *
     * Some Gherkin parser versions treat "#" after the last pipe as an additional column, others
     * silently corrupt the last cell value. Forbidding it removes the ambiguity.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkExamplesInlineComments(array $lines) : array
    {
        $errors     = [];
        $inExamples = false;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if (self::startsWith($trimmed, 'Examples:')) {
                $inExamples = true;
                continue;
            }

            // A "#"-only line is a valid comment - it skips the row, but keeps the block open.
            if ($inExamples && self::startsWith($trimmed, '#')) {
                continue;
            }

            if ($inExamples && ! self::startsWith($trimmed, '|') && $trimmed !== '') {
                $inExamples = false;
            }

            if ($inExamples && self::startsWith($trimmed, '|')) {
                $afterLastPipe = substr($trimmed, strrpos($trimmed, '|') + 1);
                if (trim($afterLastPipe) !== '' && str_contains($afterLastPipe, '#')) {
                    $errors[] = 'Line ' . ($i + 1) . ': Inline comment after the closing "|" '
                        . 'in a table row is not allowed.';
                }
            }
        }
        return $errors;
    }

    /**
     * Checks that block keywords are spelled exactly as Gherkin expects them.
     *
     * Gherkin keywords are case sensitive, so common typos like "Scenarios:" or "scenario:" are
     * not recognized as blocks - the parser then reads the line as a step and fails.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkScenarioKeywords(array $lines) : array
    {
        $errors = [];
        $validKeywords = [
            'Feature:',
            'Background:',
            'Scenario:',
            'Scenario Outline:',
            'Scenario Template:',
            'Examples:',
            'Rule:'
        ];

        // Patterns that look like mistyped block keywords
        $suspiciousPattern = '/^(scenarios?:|scenario\s+(outline|template)|backgrounds?:|examples|rules?:)/i';

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === ''
                || self::startsWith($trimmed, '#')
                || self::startsWith($trimmed, '@')
                || self::startsWith($trimmed, '|')
                || self::startsWith($trimmed, '"')
            ) {
                continue;
            }
            if (preg_match($suspiciousPattern, $trimmed) !== 1) {
                continue;
            }
            // startsWith() is case sensitive here on purpose - that is exactly what we test.
            foreach ($validKeywords as $kw) {
                if (self::startsWith($trimmed, $kw)) {
                    continue 2;
                }
            }
            $errors[] = 'Line ' . ($i + 1) . ': "' . mb_substr($trimmed, 0, 40)
                . '" looks like a misspelled Gherkin keyword. '
                . 'Valid block keywords: ' . implode(', ', $validKeywords);
        }
        return $errors;
    }

    /**
     * Ensures every DocString opening triple-quote has a matching closing one.
     *
     * An unclosed DocString makes the parser consume the rest of the file as string content,
     * which produces confusing follow-up errors on every subsequent line.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkDocStringClosure(array $lines) : array
    {
        $openLine    = null;
        $inDocString = false;

        foreach ($lines as $i => $line) {
            if (self::startsWith(trim($line), '"""')) {
                if ($inDocString) {
                    $inDocString = false;
                    $openLine    = null;
                } else {
                    $inDocString = true;
                    $openLine    = $i + 1;
                }
            }
        }

        if ($inDocString) {
            return ['Line ' . $openLine . ': DocString opened with """ but never closed.'];
        }
        return [];
    }

    /**
     * Checks that all rows of a step DataTable have the same number of columns.
     *
     * The parser accepts ragged tables without crashing, but they always mean that the step
     * receives wrong data - a mistake that is easy to overlook and hard to debug at runtime.
     *
     * Rows belonging to an Examples table are skipped here, because those are already covered by
     * checkExamplesTableConsistency() - otherwise the same line would be reported twice.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkDataTableAlignment(array $lines) : array
    {
        $errors          = [];
        $inDocString     = false;
        $inExamples      = false;
        $tableStartLine  = null;
        $tableHeaderCols = null;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if (self::startsWith($trimmed, '"""')) {
                $inDocString = ! $inDocString;
                continue;
            }
            if ($inDocString) {
                continue;
            }
            // A comment line does not break a table block.
            if (self::startsWith($trimmed, '#')) {
                continue;
            }

            if (self::startsWith($trimmed, 'Examples:')) {
                $inExamples      = true;
                $tableHeaderCols = null;
                $tableStartLine  = null;
                continue;
            }

            if (self::startsWith($trimmed, '|')) {
                // Examples tables are validated by checkExamplesTableConsistency().
                if ($inExamples) {
                    continue;
                }
                $cols = self::countTableColumns($trimmed);
                if ($tableHeaderCols === null) {
                    $tableHeaderCols = $cols;
                    $tableStartLine  = $i + 1;
                } elseif ($cols !== $tableHeaderCols) {
                    $errors[] = 'Line ' . ($i + 1) . ': DataTable row has ' . $cols
                        . ' column(s) but the table starting at line ' . $tableStartLine
                        . ' has ' . $tableHeaderCols . ' column(s).';
                }
                continue;
            }

            // Any other line ends the current table.
            $tableHeaderCols = null;
            $tableStartLine  = null;
            // ... and any non-empty line also ends the Examples block.
            if ($trimmed !== '') {
                $inExamples = false;
            }
        }
        return $errors;
    }

    /**
     * Detects duplicate tags within the tag block of the same scenario or feature.
     *
     * Duplicates do not break the parser, but they are a reliable sign of a copy-paste mistake
     * and can make a scenario match a tag filter it was never meant to match.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkDuplicateTags(array $lines) : array
    {
        $errors      = [];
        $currentTags = [];

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            // A block keyword closes the tag block that belongs to it.
            if (preg_match('/^(Feature|Background|Scenario Outline|Scenario Template|Scenario|Rule):/i', $trimmed) === 1) {
                $currentTags = [];
                continue;
            }

            if (! self::startsWith($trimmed, '@')) {
                // Any other content line means the tag block is over.
                if (! self::startsWith($trimmed, '#') && $trimmed !== '') {
                    $currentTags = [];
                }
                continue;
            }

            $withoutComment = trim(preg_replace('/#.*$/', '', $trimmed));
            $tokens = preg_split('/\s+/u', $withoutComment);
            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }
                if (isset($currentTags[$token])) {
                    $errors[] = 'Line ' . ($i + 1) . ': Duplicate tag "' . $token . '" '
                        . '(first used on line ' . $currentTags[$token] . ').';
                } else {
                    $currentTags[$token] = $i + 1;
                }
            }
        }
        return $errors;
    }

    /**
     * Checks that no steps appear after an Examples block within a Scenario Outline.
     *
     * The Examples table must be the last element of an outline. A step written after it is
     * silently ignored by some parsers and rejected by others - either way the outline does not
     * do what the author intended.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkStepsAfterExamples(array $lines) : array
    {
        $errors       = [];
        $inDocString  = false;
        $inOutline    = false;
        $inExamples   = false;
        $examplesLine = null;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if (self::startsWith($trimmed, '"""')) {
                $inDocString = ! $inDocString;
                continue;
            }
            if ($inDocString) {
                continue;
            }
            if ($trimmed === '' || self::startsWith($trimmed, '#')) {
                continue;
            }

            // A new outline resets the state.
            if (preg_match('/^Scenario\s+(Outline|Template):/i', $trimmed) === 1) {
                $inOutline    = true;
                $inExamples   = false;
                $examplesLine = null;
                continue;
            }

            // Any other block keyword closes the outline context.
            if (preg_match('/^(Feature|Background|Scenario|Rule):/i', $trimmed) === 1) {
                $inOutline    = false;
                $inExamples   = false;
                $examplesLine = null;
                continue;
            }

            if ($inOutline && self::startsWith($trimmed, 'Examples:')) {
                $inExamples   = true;
                $examplesLine = $i + 1;
                continue;
            }

            // Tags after an Examples block belong to the next scenario - reset.
            if ($inExamples && self::startsWith($trimmed, '@')) {
                $inOutline    = false;
                $inExamples   = false;
                $examplesLine = null;
                continue;
            }

            // Table rows are expected inside Examples.
            if ($inExamples && self::startsWith($trimmed, '|')) {
                continue;
            }

            if ($inExamples) {
                foreach (self::STEP_KEYWORDS as $kw) {
                    if (self::startsWith($trimmed, $kw . ' ') || $trimmed === $kw) {
                        $errors[] = 'Line ' . ($i + 1) . ': Step found after Examples block '
                            . '(Examples starts at line ' . $examplesLine . '). '
                            . 'The Examples table must be the last element of a Scenario Outline.';
                        break;
                    }
                }
            }
        }
        return $errors;
    }

    /**
     * Checks that every scenario with an Examples block is declared as a Scenario Outline.
     *
     * An Examples table only has meaning under an outline, where each row is substituted into
     * the <placeholders> of the steps. Under a plain "Scenario:" no substitution happens: the
     * placeholders stay literal and the scenario silently does the wrong thing at runtime.
     *
     * The wrong header is the root cause, so reporting it here points the author straight at the
     * fix - and it also catches mis-declared outlines without trailing steps, which
     * checkStepsAfterExamples() cannot see.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkExamplesRequireOutline(array $lines) : array
    {
        $errors          = [];
        $inDocString     = false;
        $inScenarioBlock = false;
        $isOutline       = false;
        $scenarioLine    = null;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if (self::startsWith($trimmed, '"""')) {
                $inDocString = ! $inDocString;
                continue;
            }
            if ($inDocString) {
                continue;
            }
            if ($trimmed === '' || self::startsWith($trimmed, '#')) {
                continue;
            }

            // "Scenario Outline:" / "Scenario Template:" - Examples is legal here.
            if (preg_match('/^Scenario\s+(Outline|Template):/i', $trimmed) === 1) {
                $inScenarioBlock = true;
                $isOutline       = true;
                $scenarioLine    = $i + 1;
                continue;
            }

            // Plain "Scenario:" - Examples is NOT legal here.
            if (preg_match('/^Scenario:/i', $trimmed) === 1) {
                $inScenarioBlock = true;
                $isOutline       = false;
                $scenarioLine    = $i + 1;
                continue;
            }

            // Feature/Background/Rule headers close the current scenario context.
            if (preg_match('/^(Feature|Background|Rule):/i', $trimmed) === 1) {
                $inScenarioBlock = false;
                $isOutline       = false;
                $scenarioLine    = null;
                continue;
            }

            if (preg_match('/^Examples:/i', $trimmed) === 1) {
                if (! $inScenarioBlock) {
                    $errors[] = 'Line ' . ($i + 1) . ': Examples block found outside any '
                        . 'scenario. An Examples table must belong to a Scenario Outline.';
                } elseif (! $isOutline) {
                    $errors[] = 'Line ' . ($i + 1) . ': Examples block under a plain '
                        . '"Scenario:" (header at line ' . $scenarioLine . '). A scenario that '
                        . 'uses an Examples table must be declared as "Scenario Outline:".';
                    // Treat the rest of this block as an outline, so a second Examples table
                    // does not repeat the same root-cause error for the same scenario.
                    $isOutline = true;
                }
                continue;
            }
        }

        return $errors;
    }

    /**
     * Detects unclosed quoted strings inside table cells.
     *
     * A cell like `| "Start 1 A` makes the parser misread the rest of the table and produces
     * runtime errors that point at completely unrelated places.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkUnclosedQuotesInTableCells(array $lines) : array
    {
        $errors      = [];
        $inDocString = false;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if (self::startsWith($trimmed, '"""')) {
                $inDocString = ! $inDocString;
                continue;
            }
            if ($inDocString || self::startsWith($trimmed, '#') || ! self::startsWith($trimmed, '|')) {
                continue;
            }

            foreach (self::splitTableRow($trimmed) as $cellIndex => $cell) {
                // Escaped quotes (\") are literal characters and must not be counted.
                $cellTrimmed = str_replace('\\"', '', trim($cell));
                if (substr_count($cellTrimmed, '"') % 2 !== 0) {
                    $errors[] = 'Line ' . ($i + 1) . ': Table cell ' . ($cellIndex + 1)
                        . ' contains an unclosed double-quote: ' . trim($cell);
                }
            }
        }
        return $errors;
    }

    /**
     * Checks that every table row ends with a closing "|".
     *
     * Gherkin requires table rows to be wrapped in pipes on both sides - a missing trailing pipe
     * is a parse error that stops the entire feature file.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function checkTableRowClosingPipe(array $lines) : array
    {
        $errors      = [];
        $inDocString = false;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if (self::startsWith($trimmed, '"""')) {
                $inDocString = ! $inDocString;
                continue;
            }
            if ($inDocString || self::startsWith($trimmed, '#') || ! self::startsWith($trimmed, '|')) {
                continue;
            }

            // No comment stripping here on purpose: "#" is a literal character inside table
            // cells (e.g. "| Order #5 |"), so removing it would produce false errors. A row
            // with a trailing comment is reported by checkExamplesInlineComments() instead.
            if (! self::endsWith($trimmed, '|')) {
                $errors[] = 'Line ' . ($i + 1) . ': Table row does not end with "|": ' . mb_substr($trimmed, 0, 80);
            }
        }
        return $errors;
    }

    /**
     * Splits a table row into its cells.
     *
     * Extracted into its own method because both the column counting and the quote check need
     * exactly the same splitting logic - and because getting it wrong silently falsifies every
     * table check built on top of it.
     *
     * @param string $row A single trimmed table row, e.g. "| foo | bar |"
     * @return string[]
     */
    private static function splitTableRow(string $row) : array
    {
        // Remove exactly one leading and one trailing pipe - trimming all of them would swallow
        // empty cells at the end of the row like in "| a | |".
        if (self::startsWith($row, '|')) {
            $row = substr($row, 1);
        }
        if (self::endsWith($row, '|')) {
            $row = substr($row, 0, -1);
        }
        return explode('|', $row);
    }

    /**
     * Counts the number of columns in a table row.
     *
     * Empty cells are counted too: "| a | | c |" has three columns, not two. Ignoring them would
     * make the consistency checks report mismatches for perfectly valid tables - and miss real
     * ones.
     *
     * @param string $row A single trimmed table row, e.g. "| foo | bar |"
     * @return int
     */
    private static function countTableColumns(string $row) : int
    {
        return count(self::splitTableRow($row));
    }
}