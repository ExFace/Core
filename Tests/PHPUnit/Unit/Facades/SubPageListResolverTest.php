<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\DocsFacade\DocsTemplateRenderer;
use exface\Core\Facades\DocsFacade\Placeholders\SubPageListResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Covers bare documentation lists and explicitly configured list options.
 */
class SubPageListResolverTest extends TestCase
{
    private string $directory;
    private Filesystem $filesystem;

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-subpages-' . bin2hex(random_bytes(8));
        $this->filesystem->dumpFile($this->directory . DIRECTORY_SEPARATOR . 'page.md', "# Alpha\n");
        $this->filesystem->dumpFile(
            $this->directory . DIRECTORY_SEPARATOR . 'section' . DIRECTORY_SEPARATOR . 'index.md',
            "# Section\n"
        );
        $this->filesystem->dumpFile(
            $this->directory . DIRECTORY_SEPARATOR . 'section' . DIRECTORY_SEPARATOR . 'nested.md',
            "# Nested\n"
        );
    }

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::tearDown()
     */
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
        parent::tearDown();
    }

    /**
     * A bare placeholder produces a one-level bullet list through the real renderer.
     *
     * @return void
     */
    public function testBarePlaceholderListsImmediateChildren(): void
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . 'index.md';
        $this->filesystem->dumpFile($file, "# Contents\n<!-- BEGIN SubPageList -->\n\n<!-- END SubPageList -->\n");
        $renderer = new DocsTemplateRenderer(new Workbench([
            'SERVER.INSTALLATION_NAME' => 'subpages-unit-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]));
        $renderer->addPlaceholder(new SubPageListResolver($file, $this->directory));

        self::assertSame(
            "# Contents\n<!-- BEGIN SubPageList -->\n"
                . "  - [Alpha](page.md)\n  - [Section](section/index.md)\n\n\n<!-- END SubPageList -->\n",
            $renderer->render($file)
        );
    }

    /**
     * Options may omit depth and root without warnings or losing the defaults.
     *
     * @return void
     */
    public function testFormattingOptionUsesDefaultDepthAndRoot(): void
    {
        $resolver = new SubPageListResolver($this->directory . DIRECTORY_SEPARATOR . 'index.md', $this->directory);
        $values = $resolver->resolve([
            ['name' => 'SubPageList:list-type=none', 'options' => 'list-type=none']
        ]);

        self::assertStringContainsString('<li><a href="page.md">Alpha</a></li>', $values[0]);
        self::assertStringContainsString('<li><a href="section/index.md">Section</a>', $values[0]);
        self::assertStringNotContainsString('Nested', $values[0]);
    }

    /**
     * Explicit depth values and unrelated placeholder names keep their meaning.
     *
     * @return void
     */
    public function testExplicitDepthOverridesDefault(): void
    {
        $resolver = new SubPageListResolver($this->directory . DIRECTORY_SEPARATOR . 'index.md', $this->directory);
        $values = $resolver->resolve([
            ['name' => 'SubPageList:depth=2', 'options' => 'depth=2'],
            ['name' => 'SubPageList:depth=0', 'options' => 'depth=0'],
            ['name' => 'SubPageListOther', 'options' => '']
        ]);

        self::assertStringContainsString('    - [Nested](section/nested.md)', $values[0]);
        self::assertSame('', $values[1]);
        self::assertArrayNotHasKey(2, $values);
    }
}
