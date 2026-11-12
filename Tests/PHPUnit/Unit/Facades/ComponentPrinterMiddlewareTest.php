<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Selectors\DataTypeSelector;
use exface\Core\CommonLogic\Selectors\FacadeSelector;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\MimeTypeDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Facades\HttpBadRequestError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Facades\AbstractHttpFacade\NotFoundHandler;
use exface\Core\Facades\DocsFacade;
use exface\Core\Facades\DocsFacade\MarkdownDocsReader;
use exface\Core\Facades\DocsFacade\MarkdownPrinters\GenericUxonComponentMarkdownPrinter;
use exface\Core\Facades\DocsFacade\Middleware\ComponentPrinterMiddleware;
use exface\Core\Factories\DataTypeFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Exercises registry-driven documentation without a database or mocked Workbench.
 */
class ComponentPrinterMiddlewareTest extends TestCase
{
    private string $directory;
    private ComponentPrinterMiddleware $middleware;

    /**
     * {@inheritDoc}
     * @see TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-component-docs-' . bin2hex(random_bytes(8));
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'component-docs-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        (new \ReflectionProperty(Workbench::class, 'installation_path'))->setValue($workbench, $this->directory);
        (new Filesystem())->dumpFile(
            $this->directory . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ComponentRegistry.config.json',
            json_encode([
                'COMPONENTS' => [
                    'test_type' => [
                        'selector' => [
                            'class' => DataTypeSelector::class,
                            'factory' => DataTypeFactory::class . '::createFromSelector'
                        ],
                        'documentation' => [
                            'markdown_printer_class' => GenericUxonComponentMarkdownPrinter::class
                        ]
                    ],
                    'undocumented' => (object) []
                ],
                'SOURCES' => (object) []
            ], JSON_THROW_ON_ERROR)
        );
        $facade = new DocsFacade(new FacadeSelector($workbench, DocsFacade::class));
        $this->middleware = new ComponentPrinterMiddleware(
            $facade,
            'https://example.test',
            'exface/core/Docs/Components.md',
            new MarkdownDocsReader($workbench)
        );
    }

    /**
     * {@inheritDoc}
     * @see TestCase::tearDown()
     */
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
        parent::tearDown();
    }

    /**
     * Custom registry types render through their configured factory and printer.
     */
    public function testRendersRegistryDocumentationAsRawMarkdown(): void
    {
        $url = DocsFacade::buildUrlToDocsForComponent(' TEST_TYPE ', '\\' . StringDataType::class);
        $request = new ServerRequest('GET', 'https://example.test/' . $url, [
            'Accept' => MimeTypeDataType::MARKDOWN
        ]);
        $response = $this->middleware->process($request, new NotFoundHandler());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(MimeTypeDataType::MARKDOWN, $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('# StringDataType', (string) $response->getBody());
        self::assertStringContainsString('## UXON configuration', (string) $response->getBody());
    }

    /**
     * Selectors are URL-decoded once, preserving literal percent and plus signs.
     */
    public function testPreservesEncodedSelectorValues(): void
    {
        $selector = 'example%2Fname+suffix&other=value';
        $request = new ServerRequest('GET', 'https://example.test/' . DocsFacade::buildUrlToDocsForComponent('undocumented', $selector));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No documentation found for component undocumented with selector ' . $selector);
        $this->middleware->getMarkdown($request);
    }

    /**
     * Missing, empty and array-valued parameters produce explicit request errors.
     */
    public function testRejectsInvalidParameters(): void
    {
        foreach ([
            'component=object',
            'selector=example',
            'component=&selector=example',
            'component=object&selector=%20',
            'component[]=object&selector=example',
            'component=object&selector[]=example'
        ] as $query) {
            $request = new ServerRequest('GET', 'https://example.test/api/docs/exface/core/Docs/Components.md?' . $query);
            try {
                $this->middleware->process($request, new NotFoundHandler());
                self::fail('Expected HttpBadRequestError for ' . $query);
            } catch (HttpBadRequestError $error) {
                self::assertSame(400, $error->getStatusCode());
                self::assertSame($request, $error->getRequest());
            }
        }
    }

    /**
     * Unknown types do not return an empty success response.
     */
    public function testReportsMissingDocumentation(): void
    {
        $request = new ServerRequest('GET', 'https://example.test/' . DocsFacade::buildUrlToDocsForComponent('unknown', 'example'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No documentation found for component unknown with selector example');
        $this->middleware->getMarkdown($request);
    }

    /**
     * The static usage page and unrelated pages are left to the file router.
     */
    public function testPassesThroughRequestsWithoutComponentDocumentation(): void
    {
        foreach ([
            'exface/core/Docs/Components.md',
            'exface/core/Docs/index.md?component=object&selector=example'
        ] as $path) {
            $response = $this->middleware->process(
                new ServerRequest('GET', 'https://example.test/api/docs/' . $path),
                new NotFoundHandler()
            );
            self::assertSame(404, $response->getStatusCode());
        }
    }
}
