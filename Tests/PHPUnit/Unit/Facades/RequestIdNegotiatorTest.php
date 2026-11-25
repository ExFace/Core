<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\AbstractHttpFacade\Middleware\RequestIdNegotiator;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Verifies request ID correlation without starting Workbench or accessing a database.
 */
class RequestIdNegotiatorTest extends TestCase
{
    /**
     * Requests without a header retain the ID already used by early log entries.
     */
    public function testReusesGeneratedScopeId(): void
    {
        $scope = new RequestContextScope(new Workbench([
            'SERVER.INSTALLATION_NAME' => 'request-id-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]));
        $id = $scope->getRequestId();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')
            ->with(self::callback(function (ServerRequestInterface $request) use ($id): bool {
                return $request->getHeaderLine('X-Request-ID') === $id;
            }))
            ->willReturn(new Response());

        $response = (new RequestIdNegotiator(RequestIdNegotiator::X_REQUEST_ID, $scope))
            ->process(new ServerRequest('GET', 'https://example.test'), $handler);

        self::assertSame($id, $response->getHeaderLine('X-Request-ID'));
        self::assertSame($id, $scope->getRequestId());
    }

    /**
     * An explicit client ID takes precedence over the existing scope ID.
     */
    public function testPreservesClientHeader(): void
    {
        $scope = new RequestContextScope(new Workbench([
            'SERVER.INSTALLATION_NAME' => 'request-id-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]));
        $scope->getRequestId();
        $request = new ServerRequest('GET', 'https://example.test', ['X-Request-ID' => 'client-id']);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($request))
            ->willReturn(new Response());

        $response = (new RequestIdNegotiator(RequestIdNegotiator::X_REQUEST_ID, $scope))
            ->process($request, $handler);

        self::assertSame('client-id', $response->getHeaderLine('X-Request-ID'));
    }

    /**
     * Standalone callers still get a generated ID and can use a custom header name.
     */
    public function testGeneratesIdWithoutScope(): void
    {
        $id = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $request) use (&$id): Response {
                $id = $request->getHeaderLine('X-Correlation-ID');
                return new Response();
            });

        $response = (new RequestIdNegotiator('X-Correlation-ID'))
            ->process(new ServerRequest('GET', 'https://example.test'), $handler);

        self::assertNotEmpty($id);
        self::assertSame($id, $response->getHeaderLine('X-Correlation-ID'));
    }
}
