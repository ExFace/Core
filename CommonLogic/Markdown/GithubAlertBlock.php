<?php
namespace exface\Core\CommonLogic\Markdown;

use exface\Core\Exceptions\RuntimeException;
use League\CommonMark\Node\Block\AbstractBlock;

/**
 * Block node for a GitHub-style alert box like `> [!WARNING]`.
 *
 * CommonMark parses alerts as regular blockquotes. GithubAlertExtension replaces those blockquotes
 * with this node after parsing, so that GithubAlertRenderer can produce the same HTML as GitHub
 * (`<div class="markdown-alert markdown-alert-warning">`), which is styled by github-markdown-css.
 *
 */
class GithubAlertBlock extends AbstractBlock
{
    public const TYPE_NOTE = 'note';
    public const TYPE_TIP = 'tip';
    public const TYPE_IMPORTANT = 'important';
    public const TYPE_WARNING = 'warning';
    public const TYPE_CAUTION = 'caution';

    private const TITLES = [
        self::TYPE_NOTE => 'Note',
        self::TYPE_TIP => 'Tip',
        self::TYPE_IMPORTANT => 'Important',
        self::TYPE_WARNING => 'Warning',
        self::TYPE_CAUTION => 'Caution'
    ];

    private string $alertType;

    /**
     * Creates an alert node of the given type.
     *
     * The type is validated here, because it ends up in a CSS class name and determines the title -
     * an unknown type would silently produce an unstyled alert.
     *
     * @param string $alertType one of the TYPE_xxx constants (case-insensitive)
     * @throws RuntimeException if the type is not supported
     */
    public function __construct(string $alertType)
    {
        parent::__construct();
        $alertType = strtolower($alertType);
        if (! self::isSupportedType($alertType)) {
            throw new RuntimeException('Unsupported Markdown alert type "' . $alertType . '"! Supported types: ' . implode(', ', array_keys(self::TITLES)) . '.');
        }
        $this->alertType = $alertType;
    }

    /**
     * Returns TRUE if the given type (case-insensitive) is one of the alert types supported by GitHub.
     *
     * Exists so that the extension can check markers like `[!FOO]` before creating a node and leave
     * blockquotes with unknown types untouched.
     *
     * @param string $alertType
     * @return bool
     */
    public static function isSupportedType(string $alertType) : bool
    {
        return array_key_exists(strtolower($alertType), self::TITLES);
    }

    /**
     * Returns the lowercase alert type (e.g. `warning`) as used in the CSS class `markdown-alert-<type>`.
     *
     * @return string
     */
    public function getAlertType() : string
    {
        return $this->alertType;
    }

    /**
     * Returns the title shown in the first line of the alert (e.g. `Warning`).
     *
     * The titles are the same as on GitHub regardless of how the marker was written (`[!warning]`
     * still produces `Warning`), so that rendered docs look exactly like on GitHub.
     *
     * @return string
     */
    public function getTitle() : string
    {
        return self::TITLES[$this->alertType];
    }
}
