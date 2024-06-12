<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Instrumentation\Drupal\Tests\Integration;

use ArrayObject;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;

abstract class AbstractTest extends MockeryTestCase
{
  private ScopeInterface $scope;
  /** @var ArrayObject<int, ImmutableSpan> $storage */
  protected ArrayObject $storage;

  public function setUp(): void
  {
    $this->storage = new ArrayObject();
    $tracerProvider = new TracerProvider(
      new SimpleSpanProcessor(
        new InMemoryExporter($this->storage)
      )
    );

    $this->scope = Configurator::create()
      ->withTracerProvider($tracerProvider)
      ->withPropagator(TraceContextPropagator::getInstance())
      ->activate();
  }

  public function tearDown(): void
  {
    $this->scope->detach();
  }
}