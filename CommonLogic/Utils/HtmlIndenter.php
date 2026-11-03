<?php
namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\RuntimeException;

/**
 * Indents HTML for human-readable display without modifying the markup itself.
 *  
 * This is a string-based indenter intended for debugging output (e.g. HTTP response bodies in the
 * HttpMessageDebugger). In contrast to DOMDocument and other beautifiers, it never parses the HTML
 * into a tree, so it does not repair broken markup, add or remove tags, reorder attributes or
 * convert characters to entities. Only whitespace between and around tags is changed.
 *  
 * The process has two steps:
 *  
 * 1. Tokenize the input into tags, text, comments, declarations and raw text elements
 *    (script, style, pre, textarea) by scanning the string - no large regular expressions.
 * 2. Group the tokens into lines: block tags get their own lines and change the indentation depth,
 *    while text and inline elements are kept together on one line. A block element containing only
 *    text and inline elements is put on a single line.
 *  
 * Raw text elements are kept byte-identical. Whitespace sequences in text are collapsed to a single
 * space, which does not change how HTML is rendered outside of raw text elements.
 */
class HtmlIndenter
{
    private const TOKEN_TEXT = 'text';
    private const TOKEN_OPEN = 'open';
    private const TOKEN_CLOSE = 'close';
    private const TOKEN_VOID = 'void';
    private const TOKEN_RAW = 'raw';
    private const TOKEN_OTHER = 'other';

    private const WHITESPACE_CHARS = " \t\n\r\f\x0B";

    private const RAW_TEXT_ELEMENTS = ['script', 'style', 'pre', 'textarea'];

    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen',
        'link', 'meta', 'param', 'source', 'track', 'wbr'
    ];

    private const INLINE_ELEMENTS = [
        'a', 'abbr', 'acronym', 'b', 'bdi', 'bdo', 'big', 'br', 'cite', 'code', 'data', 'del',
        'dfn', 'em', 'font', 'i', 'img', 'ins', 'kbd', 'label', 'mark', 'q', 's', 'samp', 'small',
        'span', 'strike', 'strong', 'sub', 'sup', 'time', 'tt', 'u', 'var', 'wbr'
    ];

    /**
     * Returns the given HTML with one block element per line, indented by nesting depth.
     *
     * This is the only entry point of the indenter. It never throws: if anything goes wrong
     * (e.g. a regex operation fails), the original input is returned unchanged, because a
     * debugger must always be able to show the content.
     *
     * @param string $html
     * @param string $indentWith
     * @return string
     */
    public function indent(string $html, string $indentWith = '    ') : string
    {
        try {
            return $this->buildLines($this->tokenize($html), $indentWith);
        } catch (\Throwable $e) {
            return $html;
        }
    }

    /**
     * Splits the HTML into a flat list of tokens.
     *
     * Exists to separate the recognition of markup from the layout logic. Scanning is done with
     * plain string functions, which work byte-wise and are therefore safe for any encoding and
     * for invalid UTF-8. Anything that is not a recognizable tag (e.g. "a < b") is treated as text,
     * so that no character is ever lost.
     *
     * Each token is an array with the keys `type`, `text` and (for tags) `name`.
     *
     * @param string $html
     * @return array<int, array{type: string, text: string, name?: string}>
     */
    protected function tokenize(string $html) : array
    {
        $tokens = [];
        $text = '';
        $length = strlen($html);
        $pos = 0;

        while ($pos < $length) {
            $lt = strpos($html, '<', $pos);
            if ($lt === false) {
                $text .= substr($html, $pos);
                break;
            }
            $text .= substr($html, $pos, $lt - $pos);
            $token = $this->readMarkupToken($html, $lt);
            if ($token === null) {
                // Not markup - keep the "<" as text
                $text .= '<';
                $pos = $lt + 1;
                continue;
            }
            if ($text !== '') {
                $tokens[] = ['type' => self::TOKEN_TEXT, 'text' => $text];
                $text = '';
            }
            $tokens[] = $token;
            $pos = $lt + strlen($token['text']);
        }

        if ($text !== '') {
            $tokens[] = ['type' => self::TOKEN_TEXT, 'text' => $text];
        }
        return $tokens;
    }

    /**
     * Reads the markup construct starting with the "<" at the given offset.
     *
     * Exists to recognize every kind of markup (comments, CDATA, declarations, closing tags, opening
     * tags and raw text elements) in one place. Returns NULL if the "<" does not start any markup,
     * so the caller can treat it as text. Unterminated comments, CDATA and raw text elements extend
     * to the end of the input, just like browsers handle them.
     *
     * @param string $html
     * @param int $start
     * @return array{type: string, text: string, name?: string}|null
     */
    protected function readMarkupToken(string $html, int $start) : ?array
    {
        $next = $html[$start + 1] ?? '';

        if (substr_compare($html, '<!--', $start, 4) === 0) {
            return ['type' => self::TOKEN_OTHER, 'text' => $this->substrUntil($html, $start, '-->', 4)];
        }
        if (substr_compare($html, '<![CDATA[', $start, 9) === 0) {
            return ['type' => self::TOKEN_OTHER, 'text' => $this->substrUntil($html, $start, ']]>', 9)];
        }
        if ($next === '!' || $next === '?') {
            $end = strpos($html, '>', $start);
            return $end === false ? null : ['type' => self::TOKEN_OTHER, 'text' => substr($html, $start, $end - $start + 1)];
        }
        if ($next === '/') {
            if (! ctype_alpha($html[$start + 2] ?? '')) {
                return null;
            }
            $end = strpos($html, '>', $start);
            if ($end === false) {
                return null;
            }
            return [
                'type' => self::TOKEN_CLOSE,
                'text' => substr($html, $start, $end - $start + 1),
                'name' => $this->readTagName($html, $start + 2)
            ];
        }
        if (! ctype_alpha($next)) {
            return null;
        }

        $end = $this->findTagEnd($html, $start);
        if ($end === null) {
            return null;
        }
        $tag = substr($html, $start, $end - $start + 1);
        $name = $this->readTagName($html, $start + 1);

        if (substr($tag, -2) === '/>' || in_array($name, self::VOID_ELEMENTS, true)) {
            return ['type' => self::TOKEN_VOID, 'text' => $tag, 'name' => $name];
        }
        if (in_array($name, self::RAW_TEXT_ELEMENTS, true)) {
            return ['type' => self::TOKEN_RAW, 'text' => $this->readRawElement($html, $start, $end + 1, $name), 'name' => $name];
        }
        return ['type' => self::TOKEN_OPEN, 'text' => $tag, 'name' => $name];
    }

    /**
     * Returns the substring from $start up to and including $terminator (or to the end of the input).
     *
     * Exists to read comments and CDATA sections, whose content may contain "<" and ">".
     *
     * @param string $html
     * @param int $start
     * @param string $terminator
     * @param int $skip number of bytes of the opening sequence to skip before searching
     * @return string
     */
    protected function substrUntil(string $html, int $start, string $terminator, int $skip) : string
    {
        $end = strpos($html, $terminator, $start + $skip);
        if ($end === false) {
            return substr($html, $start);
        }
        return substr($html, $start, $end - $start + strlen($terminator));
    }

    /**
     * Returns the lowercase tag name starting at the given offset.
     *
     * Exists to classify tags (inline, void, raw text) case-insensitively. The name ends at the
     * first whitespace, "/" or ">", so custom elements like `my-widget` and namespaced tags work too.
     *
     * @param string $html
     * @param int $offset
     * @return string
     */
    protected function readTagName(string $html, int $offset) : string
    {
        return strtolower(substr($html, $offset, strcspn($html, self::WHITESPACE_CHARS . '/>', $offset)));
    }

    /**
     * Returns the offset of the ">" closing the opening tag that starts at $start.
     *
     * Exists because attribute values may contain ">" (e.g. `data-x="a > b"`), so the first ">"
     * is not necessarily the end of the tag. Quotes are only honored if they are closed - for broken
     * markup with an unclosed quote, the first ">" is used instead to avoid swallowing the rest of
     * the document. Returns NULL if the tag is not terminated at all.
     *
     * @param string $html
     * @param int $start
     * @return int|null
     */
    protected function findTagEnd(string $html, int $start) : ?int
    {
        $length = strlen($html);
        $pos = $start + 1;
        while ($pos < $length) {
            $pos += strcspn($html, '>"\'', $pos);
            if ($pos >= $length) {
                return null;
            }
            $char = $html[$pos];
            if ($char === '>') {
                return $pos;
            }
            $quoteEnd = strpos($html, $char, $pos + 1);
            if ($quoteEnd === false) {
                $end = strpos($html, '>', $start);
                return $end === false ? null : $end;
            }
            $pos = $quoteEnd + 1;
        }
        return null;
    }

    /**
     * Returns a complete raw text element (opening tag, content and closing tag) as a single string.
     *
     * Exists to keep the content of script, style, pre and textarea byte-identical: it is never
     * tokenized, so "<" inside scripts or significant whitespace in pre cannot be touched. If the
     * closing tag is missing, the element extends to the end of the input.
     *
     * A match of "</" + name only counts as the closing tag if the name is followed by ">", "/" or
     * whitespace. Otherwise it is a different tag that merely starts with the same characters
     * (e.g. "</prefix>" inside a "<pre>"), and the search continues behind it.
     *
     * @param string $html
     * @param int $start offset of the opening tag
     * @param int $contentStart offset right after the opening tag
     * @param string $name lowercase tag name
     * @return string
     */
    protected function readRawElement(string $html, int $start, int $contentStart, string $name) : string
    {
        $needle = '</' . $name;
        $needleLength = strlen($needle);
        $offset = $contentStart;

        while (($closeStart = stripos($html, $needle, $offset)) !== false) {
            $after = $html[$closeStart + $needleLength] ?? '';
            $isClosingTag = $after === '>'
                || $after === '/'
                || ($after !== '' && strpos(self::WHITESPACE_CHARS, $after) !== false);

            if ($isClosingTag) {
                $closeEnd = strpos($html, '>', $closeStart);
                if ($closeEnd === false) {
                    return substr($html, $start);
                }
                return substr($html, $start, $closeEnd - $start + 1);
            }
            $offset = $closeStart + $needleLength;
        }

        return substr($html, $start);
    }

    /**
     * Groups the tokens into indented lines and joins them to the final output.
     *
     * This is the layout step of the indentation process. Every line is a list of tokens:
     *
     * - text and inline elements form one line (an "inline run"),
     * - a block element containing only text and inline elements is one line,
     * - every other block opening or closing tag, raw text element, void block element, comment,
     *   doctype and CDATA gets its own line.
     *
     * Indentation depth is derived from the tags on each line, so it never depends on the markup
     * being well-formed.
     *
     * @param array $tokens
     * @param string $indentWith
     * @return string
     */
    protected function buildLines(array $tokens, string $indentWith) : string
    {
        $lines = [];
        $depth = 0;
        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];
            if ($token['type'] === self::TOKEN_TEXT && $this->isWhitespace($token['text'])) {
                $i++;
                continue;
            }

            if ($this->isInlineToken($token)) {
                $end = $this->findInlineRunEnd($tokens, $i);
            } elseif ($token['type'] === self::TOKEN_OPEN) {
                $end = $this->findCompactBlockEnd($tokens, $i) ?? $i;
            } else {
                $end = $i;
            }

            $lineTokens = array_slice($tokens, $i, $end - $i + 1);
            [$lineOffset, $depthChange] = $this->calculateDepthChange($lineTokens);
            $line = $this->renderLine($lineTokens);
            if ($line !== '') {
                $lines[] = str_repeat($indentWith, max(0, $depth + $lineOffset)) . $line;
            }
            $depth = max(0, $depth + $depthChange);
            $i = $end + 1;
        }

        return implode("\n", $lines);
    }

    /**
     * Returns TRUE if the token can be part of an inline run (text or inline element tag).
     *
     * Exists to decide which tokens stay on the same line as the surrounding text.
     *
     * @param array $token
     * @return bool
     */
    protected function isInlineToken(array $token) : bool
    {
        switch ($token['type']) {
            case self::TOKEN_TEXT:
                return true;
            case self::TOKEN_OPEN:
            case self::TOKEN_CLOSE:
            case self::TOKEN_VOID:
                return in_array($token['name'], self::INLINE_ELEMENTS, true);
        }
        return false;
    }

    /**
     * Returns the index of the last token of the inline run starting at $start.
     *
     * Exists to keep text and inline elements on one line until a block-level token interrupts them.
     *
     * @param array $tokens
     * @param int $start
     * @return int
     */
    protected function findInlineRunEnd(array $tokens, int $start) : int
    {
        $end = $start;
        $count = count($tokens);
        while ($end + 1 < $count && $this->isInlineToken($tokens[$end + 1])) {
            $end++;
        }
        return $end;
    }

    /**
     * Returns the index of the matching closing tag if the block element at $start only contains
     * text and inline elements, or NULL otherwise.
     *
     * Exists to render simple elements like `<td>1</td>` or `<p>Hello <b>World</b></p>` on a single
     * line instead of spreading them over three lines.
     *
     * @param array $tokens
     * @param int $start index of a block opening tag
     * @return int|null
     */
    protected function findCompactBlockEnd(array $tokens, int $start) : ?int
    {
        $name = $tokens[$start]['name'];
        $count = count($tokens);
        for ($i = $start + 1; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token['type'] === self::TOKEN_CLOSE && $token['name'] === $name) {
                return $i;
            }
            if (! $this->isInlineToken($token)) {
                return null;
            }
        }
        return null;
    }

    /**
     * Computes how a line affects the indentation depth.
     *
     * Exists to derive indentation from the actual tags instead of assuming well-formed markup.
     * Returns two values:
     *
     * - the offset to apply to the current depth for this line (negative if the line starts with
     *   closing tags, so `</div>` is outdented),
     * - the net change of depth for the following lines (opened minus closed elements).
     *
     * Void elements, self-closing tags, raw text elements, comments and declarations do not change
     * the depth.
     *
     * @param array $lineTokens
     * @return array{0: int, 1: int}
     */
    protected function calculateDepthChange(array $lineTokens) : array
    {
        $change = 0;
        $min = 0;
        foreach ($lineTokens as $token) {
            if ($token['type'] === self::TOKEN_OPEN) {
                $change++;
            } elseif ($token['type'] === self::TOKEN_CLOSE) {
                $change--;
                $min = min($min, $change);
            }
        }
        return [$min, $change];
    }

    /**
     * Concatenates the tokens of a line, collapsing whitespace in text tokens and trimming the line.
     *
     * Exists to remove the original line breaks and indentation from text, so it fits into the new
     * layout. Only whitespace in text tokens is touched - tags (and thus attributes) as well as raw
     * text elements are copied verbatim. A whitespace sequence is collapsed to a single space
     * rather than removed, so the rendering of the HTML is not affected. A line consisting of a
     * single raw text element, comment or declaration is returned verbatim - even if it is
     * unterminated and ends with whitespace.
     *
     * @param array $lineTokens
     * @throws RuntimeException if collapsing whitespace fails
     * @return string
     */
    protected function renderLine(array $lineTokens) : string
    {
        if (count($lineTokens) === 1 && ($lineTokens[0]['type'] === self::TOKEN_RAW || $lineTokens[0]['type'] === self::TOKEN_OTHER)) {
            return $lineTokens[0]['text'];
        }
        $line = '';
        foreach ($lineTokens as $token) {
            if ($token['type'] === self::TOKEN_TEXT) {
                $collapsed = preg_replace('/[' . preg_quote(self::WHITESPACE_CHARS, '/') . ']+/', ' ', $token['text']);
                if ($collapsed === null) {
                    throw new RuntimeException('Cannot indent HTML: collapsing whitespace failed with PCRE error ' . preg_last_error());
                }
                $line .= $collapsed;
            } else {
                $line .= $token['text'];
            }
        }
        return trim($line, self::WHITESPACE_CHARS);
    }

    /**
     * Returns TRUE if the string consists of whitespace only.
     *
     * Exists to drop whitespace between block tags, which is replaced by the new line breaks and
     * indentation.
     *
     * @param string $text
     * @return bool
     */
    protected function isWhitespace(string $text) : bool
    {
        return strspn($text, self::WHITESPACE_CHARS) === strlen($text);
    }
}