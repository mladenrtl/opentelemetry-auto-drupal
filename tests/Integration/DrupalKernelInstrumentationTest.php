<?php

namespace OpenTelemetry\Tests\Instrumentation\Drupal\Tests\Integration;

use Closure;
use Drupal\Component\DependencyInjection\Container;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Routing\RouteProviderInterface;
use Exception;
use Mockery;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Instrumentation\Drupal\DrupalKernelInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class DrupalKernelInstrumentationTest extends BaseInstrumentationTest {

  private Request $request;

  private string $scheme;
  private $server;

  private string $uri;

  private string $method;
  private int $contentLength;
  private string $url;
  private string $host;
  private array $attributes = [];
  private string $responseContent;
  private int $responseCode;

  private string $routeName;

  public function setUp(): void {
    parent::setUp();
    $this->uri = '/test/uri';
    $this->method = 'GET';
    $this->contentLength = 12345;
    $this->scheme = 'https';
    $this->host = 'localhost';
    $this->url = sprintf('%s://%s%s', $this->scheme, $this->host, $this->uri);
    $this->attributes = [
      '_raw_variables' => new ParameterBag(['key1' => 'test', 'key2' => '2test']),
    ];
    $this->server = [
      'REQUEST_URI' => $this->uri,
      'REQUEST_METHOD' => $this->method,
      'HTTPS' => $this->scheme == 'https' ? 'on' : 'off',
      'HTTP_CONTENT_LENGTH' => $this->contentLength,
      'HTTP_HOST' => $this->host,
    ];

    $this->request = new Request([], [], $this->attributes, [], [], $this->server);
    $this->responseContent = 'sample response content';
    $this->responseCode = 200;
    $this->response = new Response($this->responseContent, $this->responseCode);
    $this->function = 'handle';
    $this->filename = '/app/docroot/core/lib/Drupal/Core/DrupalKernel.php';
    $this->lineno = 709;
    $this->class = DrupalKernel::class;
    $this->routeName = 'test_route';

    $container = Mockery::mock(Container::class);
    $routeProvider = Mockery::mock(RouteProviderInterface::class);
    $routeCollection = Mockery::mock(RouteCollection::class);
    $requestStack = Mockery::mock(RequestStack::class);
    $container->shouldReceive('get')->with('request_stack')->andReturn($requestStack);
    $container->shouldReceive('get')->with('router.route_provider')->andReturn($routeProvider);
    $requestStack->shouldReceive('push')->with($this->request)->andReturnNull();
    $routeProvider->shouldReceive('getRouteCollectionForRequest')->with($this->request)->andReturn($routeCollection);
    $routeCollection->shouldReceive('getIterator')->andReturn(new \ArrayIterator([
      $this->routeName => new Route($this->uri, ['_controller' => 'test']),
    ]));

    $this->base = Mockery::mock(DrupalKernel::class);
    $this->base->shouldReceive('getContainer')->andReturn($container);
    $this->arguments = [$this->request];
  }

  public function testPreClosure(): void {
    $this->executePreClosure(
      $this->base,
      $this->arguments,
      $this->class,
      $this->function,
      $this->filename,
      $this->lineno
    );

    /** @var \OpenTelemetry\SDK\Trace\Span $span */
    $span = Span::fromContext(Context::storage()->current());

    self::assertSame($this->function, $span->getAttribute(TraceAttributes::CODE_FUNCTION));
    self::assertSame($this->class, $span->getAttribute(TraceAttributes::CODE_NAMESPACE));
    self::assertSame($this->filename, $span->getAttribute(TraceAttributes::CODE_FILEPATH));
    self::assertSame($this->lineno, $span->getAttribute(TraceAttributes::CODE_LINENO));
    self::assertSame(SpanKind::KIND_SERVER, $span->getKind());

    self::assertSame($this->url, $span->getAttribute(TraceAttributes::HTTP_URL));
    self::assertSame($this->method, $span->getAttribute(TraceAttributes::HTTP_REQUEST_METHOD));
    self::assertSame($this->contentLength, (int) $span->getAttribute(TraceAttributes::HTTP_REQUEST_CONTENT_LENGTH));
    self::assertSame($this->scheme, $span->getAttribute(TraceAttributes::HTTP_SCHEME));

    $this->executePostClosure(
      $this->base,
      $this->arguments,
      $this->response,
      NULL
    );
  }

  public function testPostClosureNoException(): void {
    $this->executePreClosure(
      $this->base,
      $this->arguments,
      $this->class,
      $this->function,
      $this->filename,
      $this->lineno
    );

    /** @var \OpenTelemetry\SDK\Trace\Span $span */
    $span = Span::fromContext(Context::storage()->current());

    self::assertFalse($span->hasEnded());
    $this->executePostClosure(
      $this->base,
      $this->arguments,
      $this->response,
      NULL
    );
    self::assertTrue($span->hasEnded());
    self::assertTrue($span->getDuration() > 0, 'Post closure should set duration');
    self::assertSame(strlen($this->responseContent), $span->getAttribute(TraceAttributes::HTTP_RESPONSE_CONTENT_LENGTH));
//    self::assertSame('1.0', $span->getAttribute(TraceAttributes::HTTP_FLAVOR));
    self::assertSame($this->responseCode, $span->getAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE));

    self::assertSame(sprintf('%s %s %s', \strtoupper($this->scheme), $this->method, $this->routeName), $span->getName());
    self::assertSame($this->routeName, $span->getAttribute(TraceAttributes::HTTP_ROUTE));
  }

  public function testPostClosureWithExceptionNoResponse(): void {
    $errorMessage = 'Test exception';
    $exception = new Exception($errorMessage);
    $this->executePreClosure(
      $this->base,
      $this->arguments,
      $this->class,
      $this->function,
      $this->filename,
      $this->lineno
    );

    /** @var \OpenTelemetry\SDK\Trace\Span $span */
    $span = Span::fromContext(Context::storage()->current());

    self::assertFalse($span->hasEnded());
    $this->executePostClosure(
      $this->base,
      $this->arguments,
      NULL,
      $exception
    );
    self::assertTrue($span->hasEnded());
    self::assertTrue($span->getDuration() > 0, 'Post closure should set duration');

    $spanData = $span->toSpanData();
    self::assertSame('Error', $spanData->getStatus()->getCode());
    self::assertSame($errorMessage, $spanData->getStatus()->getDescription());
  }

  protected function getInstrumentationPostClosure(): Closure {
    return DrupalKernelInstrumentation::postClosure();
  }

  protected function getInstrumentationPreClosure(): Closure {
    return DrupalKernelInstrumentation::preClosure($this->getInstrumentation());
  }

}
