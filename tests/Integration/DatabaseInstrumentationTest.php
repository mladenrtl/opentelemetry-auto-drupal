<?php

namespace OpenTelemetry\Tests\Instrumentation\Drupal\Tests\Integration;

use Closure;
use Drupal\Core\Database\Connection;
use Mockery;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Instrumentation\Drupal\DatabaseInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;

class DatabaseInstrumentationTest extends BaseInstrumentationTest {

  private string $query;
  private array $queryArguments = [];
  private string $dbSystem;

  public function setUp(): void {
    parent::setUp();
    $this->query = 'SELECT [session] FROM {sessions} WHERE [sid] = :sid LIMIT 0, 1';
    $this->queryArguments = ['session_id_test_parameter'];
    $this->function = 'query';
    $this->filename = '/app/docroot/core/lib/Drupal/Core/Database/Connection.php';
    $this->lineno = 918;
    $this->class = Connection::class;
    $this->base = Mockery::mock(Connection::class);
    $this->dbSystem = 'mariadb';
    $this->arguments = [$this->query, $this->queryArguments];
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
    self::assertSame($this->dbSystem, $span->getAttribute(TraceAttributes::DB_SYSTEM));
    self::assertSame($this->query, $span->getAttribute(TraceAttributes::DB_STATEMENT));
    self::assertSame($this->queryArguments, $span->getAttribute(DatabaseInstrumentation::DB_VARIABLES));
    self::assertSame(SpanKind::KIND_CLIENT, $span->getKind());

    $this->executePostClosureWithChecks($span);
  }

  protected function getInstrumentationPostClosure(): Closure {
    return DatabaseInstrumentation::postClosure();
  }

  protected function getInstrumentationPreClosure(): Closure {
    return DatabaseInstrumentation::preClosure($this->getInstrumentation());
  }

}
