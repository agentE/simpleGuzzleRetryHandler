<?php declare(strict_types=1);
namespace Ag\Tests\Guzzle\Unit;

use Ag\Guzzle\Handler\Retry;
use Closure;
use DateTime;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RetryMiddleware;
use PHPUnit\Framework\TestCase;

final class RetryTest extends TestCase
{
    private Retry $handler;
    private Request $request;
    private Response $response;
    private RequestException $requestException;

    public function setup(): void
    {
        $this->handler = new Retry();
        $this->request = new Request('GET', 'test-url', ['X-Foo' => 'Bar'], 'Hello, World');
        $this->response = new Response(200, ['X-Foo' => 'Bar'], 'Hello, World');
        $this->requestException = new RequestException('Error Communicating with Server', $this->request);
    }

    public function testRetryInstance(): void
    {
        $this->assertInstanceOf(Retry::class, $this->handler);
    }

    public function testDeciderInstance(): void
    {
        $retryDecider = $this->handler->retryDecider();
        $this->assertInstanceOf(Closure::class, $retryDecider);
    }

    public function testDeciderReturnsFalseWithMinimalConstructor(): void
    {
        $retryDecider = $this->handler->retryDecider();
        $result = $retryDecider(5, $this->request);
        $this->assertFalse($result);
    }

    public function testDeciderReturnsFalseWithMaxConstructor(): void
    {
        $retryDecider = $this->handler->retryDecider();
        $result = $retryDecider(1, $this->request, $this->response, $this->requestException);
        $this->assertFalse($result);
    }

    public function testDeciderReturnsTrueWithConnectionException(): void
    {
        $retryDecider = $this->handler->retryDecider();
        $this->assertInstanceOf(Closure::class, $retryDecider);

        $connectException = new ConnectException('Could not connect to hostname', $this->request);

        $result = $retryDecider(1, $this->request, $this->response, $connectException);
        $this->assertTrue($result);
    }

    public function testDeciderReturnsFlaseWithResponseCodeLessThen500(): void
    {
        $retryDecider = $this->handler->retryDecider();
        $this->assertInstanceOf(Closure::class, $retryDecider);

        $response = new Response(499, ['X-Foo' => 'Bar'], 'Hello, World');

        $result = $retryDecider(1, $this->request, $response, $this->requestException);
        $this->assertFalse($result);
    }

    public function testDeciderReturnseTrueWithResponseCodeEqualTo500(): void
    {
        $retryDecider = $this->handler->retryDecider();
        $this->assertInstanceOf(Closure::class, $retryDecider);

        $response = new Response(500, ['X-Foo' => 'Bar'], 'Hello, World');

        $result = $retryDecider(1, $this->request, $response, $this->requestException);
        $this->assertTrue($result);
    }

    public function testDeciderReturnseTrueWithResponseGreaterThan500(): void
    {
        $retryDecider = $this->handler->retryDecider();
        $this->assertInstanceOf(Closure::class, $retryDecider);

        $response = new Response(501, ['X-Foo' => 'Bar'], 'Hello, World');

        $result = $retryDecider(1, $this->request, $response, $this->requestException);
        $this->assertTrue($result);
    }

    public function testDelayWithoutRetryAfterWithoutResponse(): void
    {
        $retryDelay = $this->handler->retryDelay();
        $this->assertInstanceOf(Closure::class, $retryDelay);

        for ($retries = 0; $retries < 5; $retries++) {
            $delay = $retryDelay($retries);
            $this->assertEquals(RetryMiddleware::exponentialDelay($retries), $delay);
        }
    }

    public function testDelayWithoutRetryAfterWithResponse(): void
    {
        $retryDelay = $this->handler->retryDelay();
        $this->assertInstanceOf(Closure::class, $retryDelay);

        for ($retries = 0; $retries < 5; $retries++) {
            $delay = $retryDelay($retries, $this->response);
            $this->assertEquals(RetryMiddleware::exponentialDelay($retries), $delay);
        }
    }

    public function testDelayWithRetryAfterHeaderInSeconds(): void
    {
        $retryDelay = $this->handler->retryDelay();
        $this->assertInstanceOf(Closure::class, $retryDelay);

        $retries = 2;
        $retryAfter = 3600;
        $response = new Response(Retry::TOO_MANY_REQUESTS, ['Retry-After' => $retryAfter * $retries, 'X-Foo' => 'Bar'], 'Too Many Requests');
        $delay = $retryDelay($retries, $response);

        $this->assertEquals($retryAfter * $retries * 1000, $delay);
    }

    public function testDelayWithRetryAfterHeaderAsDateString(): void
    {
        $retryDelay = $this->handler->retryDelay();
        $this->assertInstanceOf(Closure::class, $retryDelay);

        $retries = 3;
        $date = new DateTime('NOW');
        $date->modify(sprintf('+%d hour', $retries));
        $retryAfter = gmdate(DATE_COOKIE, $date->getTimestamp());

        $response = new Response(Retry::TOO_MANY_REQUESTS, ['Retry-After' => $retryAfter, 'X-Foo' => 'Bar'], 'Too Many Requests');
        $delay = $retryDelay($retries, $response);
        $this->assertEquals(3600 * 1000 * $retries, $delay);
    }
}
