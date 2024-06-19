<?php declare(strict_types=1);
namespace Ag\Guzzle\Handler;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RetryMiddleware;

class Retry
{
    const MOVED_PERMANTENTLY = 302;
    const TOO_MANY_REQUESTS = 429;
    const SERVICE_UNAVAILABLE = 503;

    private int $maxRetries;

    public function __construct(int $maxRetries = 3)
    {
        $this->maxRetries = $maxRetries;
    }

    public function retryDecider(): \Closure
    {
        return function (int $retries, Request $request, Response $response = null, GuzzleException $exception = null) {
            // Limit the number of retries
            if ($retries >= $this->maxRetries) {
                return false;
            }

            // Retry connection exceptions
            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response) {
                // Retry on server errors
                if ($response->getStatusCode() >= 500) {
                    return true;
                }
            }

            return false;
        };
    }

    public function retryDelay(): \Closure
    {
        return function (int $numberOfRetries, Response $response = null) {
            if ($response && $response->hasHeader('Retry-After') && in_array($response->getStatusCode(), [self::MOVED_PERMANTENTLY, self::TOO_MANY_REQUESTS, self::SERVICE_UNAVAILABLE])) {
                // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Retry-After
                $retryAfter = $response->getHeaderLine('Retry-After');

                $filter_options = array(
                    'options' => array('min_range' => 0)
                );
                if (filter_var($retryAfter, FILTER_VALIDATE_INT, $filter_options) === FALSE) {
                    $retryAfter = (new \DateTime($retryAfter))->getTimestamp() - time();
                }

                return (int) $retryAfter * 1000;
            }

            return RetryMiddleware::exponentialDelay($numberOfRetries);
        };
    }
}
