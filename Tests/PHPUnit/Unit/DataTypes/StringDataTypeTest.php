<?php

namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\DataTypes\StringDataType;
use PHPUnit\Framework\TestCase;

/**
 * Covers word-aware truncation when the first word exceeds the available length.
 * 
 * @author GitHub Copilot
 */
class StringDataTypeTest extends TestCase
{
    /**
     * A single oversized word falls back to character truncation.
     * 
     * @return void
     */
    public function testTruncateSingleLongWord(): void
    {
        self::assertSame('abcde', StringDataType::truncate('abcdefghijk', 5, true));
    }

    /**
     * Later words must not allow an oversized first word to exceed the limit.
     * 
     * @return void
     */
    public function testTruncateLongFirstWordWithFollowingWords(): void
    {
        self::assertSame('abcde', StringDataType::truncate('abcdefghijk second word', 5, true));
    }

    /**
     * Tool-call titles retain text and reserve room for the ellipsis.
     * 
     * @return void
     */
    public function testTruncateToolCallWithEllipsis(): void
    {
        $prompt = "FileSearchTool('Common_JS_libraries/JsonEditor.md')";

        self::assertSame(
            "FileSearchTool('Common_JS_libraries/JsonEditor." . '...',
            StringDataType::truncate($prompt, 50, true, true, true)
        );
    }

    /**
     * The fallback counts characters rather than bytes.
     * 
     * @return void
     */
    public function testTruncateLongMultibyteWord(): void
    {
        self::assertSame(
            str_repeat("\u{00E4}", 5),
            StringDataType::truncate(str_repeat("\u{00E4}", 8), 5, true)
        );
    }

    /**
     * Existing word boundaries remain preferred when they fit.
     * 
     * @return void
     */
    public function testTruncatePreservesFittingWords(): void
    {
        self::assertSame('hello', StringDataType::truncate('hello world', 8, true));
        self::assertSame('hello', StringDataType::truncate('hello world', 5, true));
    }
}
