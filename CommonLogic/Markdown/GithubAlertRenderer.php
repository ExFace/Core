<?php
namespace exface\Core\CommonLogic\Markdown;

use exface\Core\Exceptions\RuntimeException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders a GithubAlertBlock as HTML identical to GitHub's alert boxes (without the octicon icons).
 *
 * Output example:
 *
 * ```
 * <div class="markdown-alert markdown-alert-warning">
 * <p class="markdown-alert-title">Warning</p>
 * <p>This action cannot be undone.</p>
 * </div>
 * ```
 *
 * Using GitHub's markup allows to reuse the alert styles of github-markdown-css (5.5.0+) instead
 * of maintaining own CSS.
 *
 */
class GithubAlertRenderer implements NodeRendererInterface
{
    /**
     * Renders the alert box with its title paragraph followed by the rendered child blocks.
     *
     * {@inheritDoc}
     * @see \League\CommonMark\Renderer\NodeRendererInterface::render()
     * @throws RuntimeException if called for a node, that is not a GithubAlertBlock
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer) : \Stringable
    {
        if (! $node instanceof GithubAlertBlock) {
            throw new RuntimeException('Cannot render Markdown node of type ' . get_class($node) . ' as GitHub alert!');
        }

        $attrs = $node->data->get('attributes');
        $type = $node->getAlertType();
        $attrs['class'] = trim(($attrs['class'] ?? '') . ' markdown-alert markdown-alert-' . $type);

        $separator = $childRenderer->getInnerSeparator();
        $title = new HtmlElement('p', ['class' => 'markdown-alert-title'], $node->getTitle());
        $content = $childRenderer->renderNodes($node->children());

        return new HtmlElement(
            'div',
            $attrs,
            $separator . $title . ($content === '' ? '' : $separator . $content) . $separator
        );
    }
}
