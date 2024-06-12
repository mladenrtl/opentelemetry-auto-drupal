<?php

namespace OpenTelemetry\Tests\Instrumentation\Drupal\Tests\Integration;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Instrumentation\Drupal\HttpClientCallInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;

class HttpClientCallInstrumentationTest extends BaseInstrumentationTest {

  private string $requestMethod = 'get';
  private string $requestHost = 'https://api.demo.site';

  public function setUp(): void {
    parent::setUp();
    $this->function = '__call';
    $this->filename = '/app/vendor/guzzlehttp/guzzle/src/Client.php';
    $this->lineno = 84;
    $this->class = Client::class;
    $this->base = Mockery::mock(Client::class);
    $this->arguments = [$this->requestMethod, $this->requestHost];
    $this->response = new Response(200, [], 'test');
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
    $expectedHostname = parse_url($this->requestHost, PHP_URL_HOST);

    self::assertSame($this->function, $span->getAttribute(TraceAttributes::CODE_FUNCTION));
    self::assertSame($this->class, $span->getAttribute(TraceAttributes::CODE_NAMESPACE));
    self::assertSame($this->filename, $span->getAttribute(TraceAttributes::CODE_FILEPATH));
    self::assertSame($this->lineno, $span->getAttribute(TraceAttributes::CODE_LINENO));
    self::assertSame(SpanKind::KIND_CLIENT, $span->getKind());
    self::assertSame($this->requestMethod, $span->getAttribute(TraceAttributes::HTTP_REQUEST_METHOD));
    self::assertSame($this->requestHost, $span->getAttribute(TraceAttributes::URL_FULL));
    $this->executePostClosureWithChecks($span);
  }

  public function testPostClosureNoExceptionWithResponse(): void {
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
    self::assertSame(200, $span->getAttribute(TraceAttributes::HTTP_STATUS_CODE));
  }

  protected function getInstrumentationPostClosure(): Closure {
    return HttpClientCallInstrumentation::postClosure();
  }

  protected function getInstrumentationPreClosure(): Closure {
    return HttpClientCallInstrumentation::preClosure($this->getInstrumentation());
  }

}
