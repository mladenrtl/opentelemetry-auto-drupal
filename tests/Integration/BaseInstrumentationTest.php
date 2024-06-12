<?php

namespace OpenTelemetry\Tests\Instrumentation\Drupal\Tests\Integration;

use Closure;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\Context;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Throwable;

abstract class BaseInstrumentationTest extends AbstractTest {

  protected array $arguments = [];
  protected string $function;
  protected string $filename;
  protected int $lineno;
  protected string $class;
  protected MockInterface|LegacyMockInterface $base;
  protected mixed $response = 0;

  public function testPostClosureNoException(): void {
    $this->executePreClosure(
      $this->base,
      $this->arguments,
      $this->class,
      $this->function,
      $this->filename,
      $this->lineno
    );

    /** @var SpanInterface $span */
    $span = Span::fromContext(Context::storage()->current());

    $this->executePostClosureWithChecks($span);
  }

  public function testPostClosureWithException(): void {
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
//    $span = Span::fromContext(Context::storage()->current());
    $span = Span::fromContext(Context::getCurrent());

    $this->executePostClosureWithChecks($span, $exception);

    $spanData = $span->toSpanData();
    self::assertSame('Error', $spanData->getStatus()->getCode());
    self::assertSame($errorMessage, $spanData->getStatus()->getDescription());
  }

  abstract protected function getInstrumentationPostClosure(): Closure;

  abstract protected function getInstrumentationPreClosure(): Closure;

  protected function executePreClosure(mixed $base, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
    $preClosure = $this->getInstrumentationPreClosure();
    $preClosure(
      $base,
      $params,
      $class,
      $function,
      $filename,
      $lineno
    );
  }

  protected function executePostClosure(mixed $base, array $params, mixed $returnValue, ?Throwable $exception): void {
    $postClosure = $this->getInstrumentationPostClosure();
    $postClosure(
      $base,
      $params,
      $returnValue,
      $exception
    );
  }

  /**
   * @param \OpenTelemetry\SDK\Trace\Span $span
   * @param Exception $exception
   * @return void
   */
  public function executePostClosureWithChecks(\OpenTelemetry\SDK\Trace\Span $span, ?Exception $exception = null): void
  {
    self::assertFalse($span->hasEnded());
    $this->executePostClosure(
      $this->base,
      $this->arguments,
      $this->response,
      $exception
    );
    self::assertTrue($span->hasEnded());
    self::assertTrue($span->getDuration() > 0, 'Post closure should set duration');
  }

  protected function getInstrumentation(): CachedInstrumentation {
    return new CachedInstrumentation('io.opentelemetry.contrib.php.drupal');
  }

  #[Before]
  protected function startMockery()
    {
        $this->mockeryOpen = true;
    }

    #[After]
    protected function purgeMockeryContainer()
    {
        if ($this->mockeryOpen) {
            // post conditions wasn't called, so test probably failed
            Mockery::close();
        }
    }
}
