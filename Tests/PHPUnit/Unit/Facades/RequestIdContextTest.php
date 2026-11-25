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
 * Verifies request context synchronization before downstream middleware executes.
 */
class RequestIdContextTest extends TestCase
{
    /**
     * External IDs replace the generated scope ID and explicit subrequests override embedded ones.
     */
    public function testSynchronizesCustomHeaderAndSubrequestBeforeHandling(): void
    {
        $scope = new RequestContextScope(new Workbench([
            'SERVER.INSTALLATION_NAME' => 'request-id-context-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]));
        $scope->getRequestId();
        $request = new ServerRequest('GET', 'https://example.test', [
            'x-correlation-id' => 'client:embedded',
            'x-request-id-subrequest' => 'child'
        ]);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $received) use ($scope, $request): Response {
                self::assertSame($request, $received);
                self::assertSame('client:child', $scope->getRequestId());
                self::assertSame('child', $scope->getSubrequestId());
                return new Response();
            });

        $response = (new RequestIdNegotiator('X-Correlation-ID', $scope))->process($request, $handler);

        self::assertSame('client:embedded', $response->getHeaderLine('X-Correlation-ID'));
    }

    /**
     * Embedded subrequest IDs are adopted without replacing an explicit response header.
     */
    public function testSynchronizesEmbeddedSubrequestAndPreservesResponseHeader(): void
    {
        $scope = new RequestContextScope(new Workbench([
            'SERVER.INSTALLATION_NAME' => 'request-id-context-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]));
        $request = new ServerRequest('GET', 'https://example.test', ['X-Request-ID' => 'client:embedded']);
        $expectedResponse = new Response(200, ['X-Request-ID' => 'response-id']);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $received) use ($scope, $expectedResponse): Response {
                self::assertSame('client:embedded', $scope->getRequestId());
                self::assertSame('embedded', $scope->getSubrequestId());
                return $expectedResponse;
            });

        $response = (new RequestIdNegotiator(RequestIdNegotiator::X_REQUEST_ID, $scope))->process($request, $handler);

        self::assertSame($expectedResponse, $response);
    }
}
