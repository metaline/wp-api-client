<?php

/*
 * This file is part of the WP API Client library.
 *
 * (c) Meta Line Srl
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MetaLine\WordPressAPIClient\Tests;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use MetaLine\WordPressAPIClient\Exception\ApiException;
use MetaLine\WordPressAPIClient\Client;
use MetaLine\WordPressAPIClient\Exception\ResourceNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SplFileObject;

class ClientTest extends TestCase
{
    use ClientTrait;

    public function testSuccessfulResponse()
    {
        $response = new Response(200, [], '{"message":"OK"}');
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')
            ->with('GET', 'successful', [])
            ->willReturn($response);

        $client = new Client($guzzle);
        $result = $client->get('successful');

        $this->assertEquals(['message' => 'OK'], $result);
    }

    /**
     * @dataProvider requestGetProvider
     */
    public function testSendDataInQueryString(string $uri, array $query, string $expectedUri)
    {
        $response = new Response(200, [], '{"message":"OK"}');
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')
            ->with('GET', $expectedUri, [])
            ->willReturn($response);

        $client = new Client($guzzle);
        $result = $client->get($uri, $query);

        $this->assertEquals(['message' => 'OK'], $result);
    }

    public function requestGetProvider(): iterable
    {
        yield [
            'path/to/api/call',
            ['key' => 'value'],
            'path/to/api/call?key=value',
        ];

        yield [
            'path/to/api/call?foo=bar',
            ['key' => 'value'],
            'path/to/api/call?foo=bar&key=value',
        ];

        yield [
            'path/to/api/call?foo=bar&key=baz',
            ['key' => 'value'],
            'path/to/api/call?foo=bar&key=value',
        ];
    }

    /**
     * @dataProvider requestPostProvider
     */
    public function testSendDataInBody(array $data, array $guzzleOptions)
    {
        $response = new Response(201, [], '{"message":"Resource created"}');
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')
            ->with('POST', 'create-post', $guzzleOptions)
            ->willReturn($response);

        $client = new Client($guzzle);
        $result = $client->post('create-post', $data);

        $this->assertEquals(['message' => 'Resource created'], $result);
    }

    public function requestPostProvider(): iterable
    {
        // Simple JSON
        yield [
            ['name' => 'New Post'],
            ['json' => ['name' => 'New Post']],
        ];

        // Multipart
        $path = __DIR__ . '/fixtures/spacer.gif';
        yield [
            [
                'file'  => new SplFileObject($path),
                'title' => 'Do you remember the spacer.gif?',
            ],
            [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => file_get_contents($path),
                        'filename' => 'spacer.gif',
                    ],
                    [
                        'name'     => 'title',
                        'contents' => 'Do you remember the spacer.gif?',
                    ],
                ],
            ],
        ];
    }

    public function testGuzzleExceptionIsConverted()
    {
        $exception = new class() extends Exception implements GuzzleException {};
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')->willThrowException($exception);

        $this->expectException(ApiException::class);

        $client = new Client($guzzle);
        $client->get('guzzle-exception');
    }

    public function testResourceNotFoundExceptionOn404()
    {
        $response = new Response(404, [], '{}');
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')->willReturn($response);

        $this->expectException(ResourceNotFoundException::class);

        $client = new Client($guzzle);
        $client->get('404');
    }

    /**
     * @dataProvider failedStatusCodeProvider
     */
    public function testApiExceptionOnFailureResponse(int $statusCode)
    {
        $response = new Response($statusCode, [], '{}');
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')->willReturn($response);

        $this->expectException(ApiException::class);

        $client = new Client($guzzle);
        $client->get('failure');
    }

    public function failedStatusCodeProvider(): iterable
    {
        yield [400];
        yield [401];
        yield [403];
        yield [500];
        yield [503];
    }

    public function testWP_ErrorResponseThrowsApiException()
    {
        $wpErrorResponseBody = '{"code":"rest_missing_callback_param","message":"Parametro(i) mancante(i): code","data":{"status":400,"params":["code"]}}';
        $response = new Response(200, [], $wpErrorResponseBody);
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')->willReturn($response);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Error from request GET error, response body: $wpErrorResponseBody");

        $client = new Client($guzzle);
        $client->get('error');
    }

    /**
     * @dataProvider invalidResultProvider
     */
    public function testInvalidResult(string $result)
    {
        $response = new Response(200, [], $result);
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')->willReturn($response);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('#^Invalid result from request GET invalid, response body:#');

        $client = new Client($guzzle);
        $client->get('invalid');
    }

    public function invalidResultProvider(): iterable
    {
        yield ['']; // empty
        yield ['""']; // empty string
        yield ['"simple string"']; // simple string
        yield ['null']; // null
        yield ['true']; // boolean true
        yield ['false']; // boolean false
    }

    public function testRetriesWhenConnectionRefused()
    {
        $request = $this->createMock(RequestInterface::class);
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle
            ->expects($this->exactly(5))
            ->method('request')
            ->willThrowException(new ConnectException('Connection refused', $request));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Connection refused');

        $client = new Client($guzzle);
        $client->post('test');
    }

    public function testRetriesOnServerError()
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle
            ->expects($this->exactly(5))
            ->method('request')
            ->willThrowException(new ServerException('Server maintenance', $request, $response));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Server maintenance');

        $client = new Client($guzzle);
        $client->post('test');
    }
}
