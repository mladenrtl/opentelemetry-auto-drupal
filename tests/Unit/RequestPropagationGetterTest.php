<?php

namespace OpenTelemetry\Tests\Instrumentation\Drupal\Tests\Unit;

use InvalidArgumentException;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use OpenTelemetry\Contrib\Instrumentation\Drupal\RequestPropagationGetter;
use Symfony\Component\HttpFoundation\Request;

class RequestPropagationGetterTest extends MockeryTestCase {

  public function testWithValidRequest() {
    $server = [
      'REQUEST_URI' => 'testUri',
      'REQUEST_METHOD' => 'GET',
      'HTTPS' => 'on',
      'HTTP_CONTENT_LENGTH' => 1234,
      'HTTP_HOST' => 'testhost',
      'HTTP_METHOD' => 'GET',
      'HTTP_PATH' => 'testpath',
      'HTTP_SCHEME' => 'https',
    ];

    $expectedKeys = ['content-length', 'host', 'method', 'path', 'scheme'];
    $request = new Request([], [], [], [], [], $server);
    $prop = RequestPropagationGetter::instance();
    $keys = $prop->keys($request);

    self::assertSame($expectedKeys, $keys);
    self::assertSame('1234', $prop->get($request, 'content-length'));
    self::assertSame('testhost', $prop->get($request, 'host'));
    self::assertSame('GET', $prop->get($request, 'method'));
    self::assertSame('testpath', $prop->get($request, 'path'));
    self::assertSame('https', $prop->get($request, 'scheme'));
  }

  public function testWithException() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Unsupported carrier type: array. Unable to get value associated with key:key');
    $server = [
      'REQUEST_URI' => 'testUri',
      'REQUEST_METHOD' => 'GET',
      'HTTPS' => 'on',
      'HTTP_CONTENT_LENGTH' => 1234,
      'HTTP_HOST' => 'testhost',
      'HTTP_METHOD' => 'GET',
      'HTTP_PATH' => 'testpath',
      'HTTP_SCHEME' => 'https',
    ];

    $expectedKeys = ['content-length', 'host', 'method', 'path', 'scheme'];
    $request = new Request([], [], [], [], [], $server);
    $prop = RequestPropagationGetter::instance();
    $keys = $prop->keys($request);

    self::assertSame($expectedKeys, $keys);
    $prop->get(['key' => 'value'], 'key');
  }

  public function testWithIncorrectObject() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Unsupported carrier type: stdClass.');
    $prop = RequestPropagationGetter::instance();
    $prop->keys(new \stdClass());
  }

}
