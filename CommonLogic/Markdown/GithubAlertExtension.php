<?php
namespace exface\Core\CommonLogic\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\NodeIterator;

/**
 * league/commonmark extension for GitHub-style alert boxes.
 *
 * GitHub renders blockquotes starting with a marker like `[!WARNING]` on their own first line as
 * colored alert boxes:
 *
 * ```
 * > [!WARNING]
 * > This action cannot be undone.
 * ```
 *
 * Supported types: `NOTE`, `TIP`, `IMPORTANT`, `WARNING`, `CAUTION` (case-insensitive). CommonMark
 * itself has no such syntax, so without this extension the marker would be rendered as plain text
 * inside a regular blockquote.
 *
 * The extension does not add a block parser. Instead it post-processes the parsed document and
 * replaces matching blockquotes with GithubAlertBlock nodes. This keeps all regular blockquote
 * parsing (lazy continuation, nesting, etc.) exactly as in CommonMark. Everything not matching the
 * GitHub syntax stays a regular blockquote:
 *
 * - unknown types like `[!FOO]`
 * - markers followed by other text on the same line
 * - markers inside code spans or code blocks
 * - markers without any content after them
 */
class GithubAlertExtension implements ExtensionInterface
{
    private const MARKER_PATTERN = '/^\[!([a-z]+)\]$/i';

    /**
     * Registers the post-processing listener and the renderer for alert nodes.
     *
     * {@inheritDoc}
     * @see \League\CommonMark\Extension\ExtensionInterface::register()
     */
    public function register(EnvironmentBuilderInterface $environment) : void
    {
        $environment
            ->addEventListener(DocumentParsedEvent::class, [$this, 'onDocumentParsed'])
            ->addRenderer(GithubAlertBlock::class, new GithubAlertRenderer());
    }

    /**
     * Replaces every blockquote using the alert syntax with a GithubAlertBlock.
     *
     * This must happen after the whole document was parsed, because only then the inline content of
     * the first paragraph is available to detect the marker. The blockquotes are collected first and
     * transformed afterwards, because replacing nodes while iterating would break the iterator.
     *
     * @param DocumentParsedEvent $event
     * @return void
     */
    public function onDocumentParsed(DocumentParsedEvent $event) : void
    {
        $blockquotes = [];
        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if ($node instanceof BlockQuote) {
                $blockquotes[] = $node;
            }
        }
        foreach ($blockquotes as $blockquote) {
            $this->convertBlockQuote($blockquote);
        }
    }

    /**
     * Turns the given blockquote into an alert if it starts with a valid marker on its own line.
     *
     * The marker and the line break after it are removed, so the alert contains only the actual
     * content. If the marker is followed by a blank line (`> [!NOTE]`, `>`, `> Text`), the whole
     * marker paragraph is removed. If nothing is left after removing the marker, the blockquote is
     * kept as-is, because GitHub does not render empty alerts either.
     *
     * @param BlockQuote $blockquote
     * @return void
     */
    protected function convertBlockQuote(BlockQuote $blockquote) : void
    {
        $paragraph = $blockquote->firstChild();
        if (! $paragraph instanceof Paragraph) {
            return;
        }
        $marker = $paragraph->firstChild();
        if (! $marker instanceof Text) {
            return;
        }
        $lineEnd = $marker->next();
        if ($lineEnd !== null && ! $lineEnd instanceof Newline) {
            return;
        }
        if (! preg_match(self::MARKER_PATTERN, trim($marker->getLiteral()), $matches)) {
            return;
        }
        $type = $matches[1];
        if (! GithubAlertBlock::isSupportedType($type)) {
            return;
        }

        // The alert must have content. Without a line break the marker paragraph contains nothing
        // else, so the content must come from subsequent blocks.
        if ($lineEnd === null && $paragraph->next() === null) {
            return;
        }

        $alert = new GithubAlertBlock($type);
        $alert->setStartLine($blockquote->getStartLine());
        $alert->setEndLine($blockquote->getEndLine());
        // Keep attributes, that other extensions might have added to the blockquote
        $alert->data->import($blockquote->data->export());

        $marker->detach();
        if ($lineEnd !== null) {
            $lineEnd->detach();
        } else {
            $paragraph->detach();
        }

        foreach ($blockquote->children() as $child) {
            $alert->appendChild($child);
        }
        $blockquote->replaceWith($alert);
    }
}
