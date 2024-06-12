<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\Drupal\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

final class HttpClientRequestInstrumentationTest extends AbstractTest
{
  public static function setUpBeforeClass(): void
  {
    TestHttpServer::start();
  }

  protected function getHttpClient(string $testCase): ClientInterface
  {
    return new Client(['verify_peer' => false, 'verify_host' => false]);
  }

  /**
   * @dataProvider requestProvider
   */
  public function test_send_request(string $method, string $uri, int $statusCode, string $spanStatus): void
  {
    $client = $this->getHttpClient(__FUNCTION__);
    $this->assertCount(0, $this->storage);

    $response = $client->request($method, $uri, ['bindto' => '127.0.0.1:9876']);
    $response->getStatusCode();
    $this->assertCount(1, $this->storage);

    /** @var ImmutableSpan $span */
    $span = $this->storage[0];

    if ($method === 'GET') {
      $requestHeaders = json_decode($response->getBody()->getContents(), true);
      $this->assertNotNull($requestHeaders['HTTP_USER_AGENT']);
    }

    $this->assertTrue($span->getAttributes()->has(TraceAttributes::URL_FULL));
    $this->assertSame($uri, $span->getAttributes()->get(TraceAttributes::URL_FULL));
    $this->assertTrue($span->getAttributes()->has(TraceAttributes::HTTP_REQUEST_METHOD));
    $this->assertSame($method, $span->getAttributes()->get(TraceAttributes::HTTP_REQUEST_METHOD));
    $this->assertTrue($span->getAttributes()->has(TraceAttributes::HTTP_RESPONSE_STATUS_CODE));
    $this->assertSame($spanStatus, $span->getStatus()->getCode());
    $this->assertSame($statusCode, $span->getAttributes()->get(TraceAttributes::HTTP_RESPONSE_STATUS_CODE));
  }

  public function requestProvider(): array
  {
    return [
      ['GET', 'http://localhost:8057', Response::HTTP_OK, StatusCode::STATUS_UNSET],
      ['POST','http://localhost:8057/json', Response::HTTP_OK, StatusCode::STATUS_UNSET],
      ['DELETE', 'http://localhost:8057/1', Response::HTTP_OK, StatusCode::STATUS_UNSET],
    ];
  }
}
