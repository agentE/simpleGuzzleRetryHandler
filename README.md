# Simple Retry Strategy for Guzzle 7

![Shields](https://img.shields.io/badge/PHP-Simple%20Guzzle%20Retry%20Handler-green)
![Shields](https://img.shields.io/badge/License-GPL-teal)

APIs might implement rate limiting, and if they do your clients might experience `429 Too Many Requests` responses with a `Retry-After` header, informing your client how long it should wait before making the next request.

[Guzzle](https://gihub.com/guzzle/guzzle) includes a [retry middleware](https://github.com/guzzle/guzzle/blob/master/src/RetryMiddleware.php) class that can be used to handle this.

The implementation in this project is a PoC, so feel free to build upon it, and comment if you think something should be added / removed.

## Example usage

~~~php
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Ag\Guzzle\Handler\Retry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

class TestRetry
{
    public function test(int $maxRetries = 5)
    {
        $handlerStack = HandlerStack::create(new CurlHandler());
        $retry = new Retry($maxRetries);
        $handlerStack->push(Middleware::retry($retry->retryDecider(), $retry->retryDelay()));

        $client = new Client(array('handler' => $handlerStack));

        $response = $client->request(
            'GET',
            // @ToDo replace to a real url!!!
            'https://some-url-here'
        )->getBody()->getContents();

        return print_r($response, true);
    }
}

try {
    $TestRetry = new TestRetry();
    $response = $TestRetry->test(5);
    echo $response . PHP_EOL;
} catch (ConnectException $e) {
    echo $e->getMessage() . PHP_EOL;
}
~~~
