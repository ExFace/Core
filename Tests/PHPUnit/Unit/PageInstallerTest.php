<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\AppInstallers\PageInstaller;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Interfaces\Model\UiPageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Exercises page export persistence without a database or a mocked Workbench.
 */
class PageInstallerTest extends TestCase
{
    private string $directory;
    private Filemanager $fileManager;

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp() : void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-pages-' . bin2hex(random_bytes(8));
        $this->fileManager = new Filemanager(new Workbench());
    }

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::tearDown()
     */
    protected function tearDown() : void
    {
        $this->fileManager->remove($this->directory);
        parent::tearDown();
    }

    /**
     * Audit-only changes must preserve both the exact bytes and modification time.
     * 
     * @return void
     */
    public function testAuditOnlyChangesLeaveFileUntouched() : void
    {
        $previous = $this->pageDefinition();
        $contents = json_encode($previous, JSON_THROW_ON_ERROR);
        $file = $this->directory . DIRECTORY_SEPARATOR . 'test.App.page.json';
        $this->fileManager->dumpFile($file, $contents);
        touch($file, 946684800);

        foreach (['created_on', 'created_by_user_selector', 'modified_on', 'modified_by_user_selector'] as $property) {
            $current = $previous;
            $current[$property] = 'changed';
            $this->export([$current]);
            clearstatcache(true, $file);
            $this->assertSame($contents, file_get_contents($file));
            $this->assertSame(946684800, filemtime($file));
        }

        $current = $previous;
        unset($current['created_on'], $current['created_by_user_selector']);
        $this->export([$current]);
        $this->assertSame($contents, file_get_contents($file));
    }

    /**
     * Real changes export the complete current definition, including audit metadata.
     * 
     * @return void
     */
    public function testPageChangesWriteFullCurrentDefinition() : void
    {
        $previous = $this->pageDefinition();
        $changes = [
            ['name' => 'Renamed'],
            ['menu_index' => 2],
            ['contents' => ['widget_type' => 'Text', 'text' => 'Updated']],
            ['contents' => ['widget_type' => 'Text', 'modified_on' => 'nested change']]
        ];
        foreach ($changes as $change) {
            $this->export([$previous]);
            $current = array_replace($previous, $change, ['modified_on' => '2026-11-13 12:00:00']);
            $this->export([$current]);
            $file = $this->directory . DIRECTORY_SEPARATOR . 'test.App.page.json';
            $this->assertSame($current, json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * New pages are written and obsolete aliases disappear, including an empty export.
     * 
     * @return void
     */
    public function testNewRenamedAndDeletedPages() : void
    {
        $page = $this->pageDefinition();
        $this->export([$page]);
        $oldFile = $this->directory . DIRECTORY_SEPARATOR . 'test.App.page.json';
        $this->assertFileExists($oldFile);

        $page['alias_with_namespace'] = 'test.App.renamed';
        $this->export([$page]);
        $newFile = $this->directory . DIRECTORY_SEPARATOR . 'test.App.renamed.json';
        $this->assertFileDoesNotExist($oldFile);
        $this->assertSame($page, json_decode(file_get_contents($newFile), true, 512, JSON_THROW_ON_ERROR));

        $this->export([]);
        $this->assertFileDoesNotExist($newFile);
    }

    /**
     * Invalid previous JSON must surface an error rather than silently overwrite the file.
     * 
     * @return void
     */
    public function testCorruptedPreviousExportIsNotOverwritten() : void
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . 'test.App.page.json';
        $this->fileManager->dumpFile($file, '{invalid');
        try {
            $this->expectException(InstallerRuntimeError::class);
            $this->export([$this->pageDefinition()]);
        } finally {
            $this->assertSame('{invalid', file_get_contents($file));
        }
    }

    /**
     * Supplies an isolated page definition rather than reading the installed metamodel.
     * 
     * @return array
     */
    private function pageDefinition() : array
    {
        return [
            'uid' => '0x123',
            'alias_with_namespace' => 'test.App.page',
            'name' => 'Page',
            'menu_index' => 1,
            'created_on' => '2025-01-01 12:00:00',
            'created_by_user_selector' => 'creator',
            'modified_on' => '2025-01-02 12:00:00',
            'modified_by_user_selector' => 'editor',
            'contents' => ['widget_type' => 'Text', 'text' => 'Original']
        ];
    }

    /**
     * Invokes the real filesystem export step without starting the database-backed backup lifecycle.
     * 
     * @param array[] $definitions
     * @return void
     */
    private function export(array $definitions) : void
    {
        $pages = [];
        foreach ($definitions as $definition) {
            $page = $this->createMock(UiPageInterface::class);
            $page->method('getUid')->willReturn($definition['uid']);
            $page->method('getAliasWithNamespace')->willReturn($definition['alias_with_namespace']);
            $page->method('exportUxonObject')->willReturn(new UxonObject($definition));
            $pages[] = $page;
        }
        $installer = (new \ReflectionClass(PageInstaller::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(PageInstaller::class, 'exportPages');
        $method->setAccessible(true);
        $method->invoke($installer, $pages, $this->directory, $this->fileManager);
    }
}
