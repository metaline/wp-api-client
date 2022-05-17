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

use InvalidArgumentException;
use MetaLine\WordPressAPIClient\ApiExceptionInterface;
use MetaLine\WordPressAPIClient\ClientInterface;
use MetaLine\WordPressAPIClient\Exception\ApiException;
use MetaLine\WordPressAPIClient\Exception\ResourceNotFoundException;
use MetaLine\WordPressAPIClient\LoggedClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;
use SplFileObject;

class LoggedClientTest extends TestCase
{
    use ClientTrait;

    private TestLogger $logger;

    private ClientInterface $innerClient;

    protected function setUp(): void
    {
        $this->logger = new TestLogger();
        $this->innerClient = $this->createMock(ClientInterface::class);
    }

    /**
     * @dataProvider logLevelsProvider
     */
    public function testSuccessfulRequestIsLoggedAsInfo($logLevel)
    {
        $requestMethod = 'GET';
        $requestPath = 'success';
        $result = ['message' => 'OK'];

        $this->innerClient
            ->method('request')
            ->with($requestMethod, $requestPath)
            ->willReturn($result);

        $client = new LoggedClient($this->innerClient, $this->logger);

        if ($logLevel) {
            $client->setSuccessfulRequestLevel($logLevel);
        } else {
            $logLevel = LogLevel::INFO;
        }

        $client->get($requestPath);

        $expectedRecord = [
            'message' => "[API] Call \"$requestMethod $requestPath\"",
            'context' => [
                'request' => [
                    'method' => $requestMethod,
                    'uri'    => $requestPath,
                    'data'   => [],
                    'query'  => [],
                ],
                'result'  => $result,
            ],
        ];

        $this->assertTrue($this->logger->hasRecord($expectedRecord, $logLevel));
    }

    public function logLevelsProvider(): iterable
    {
        yield [null];
        yield [LogLevel::DEBUG];
        yield [LogLevel::INFO];
        yield [LogLevel::NOTICE];
    }

    public function testSuccessfulRequestLevelMustBeValid()
    {
        $client = new LoggedClient($this->innerClient, $this->logger);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('\'foo\' is not a valid log level! Use one of the constants of the Psr\Log\LogLevel class.');
        $client->setSuccessfulRequestLevel('foo');
    }

    /**
     * @dataProvider exceptionsProvider
     */
    public function testExceptionsAreLoggedWithCorrectLevel(
        string $requestMethod,
        string $requestPath,
        \Exception $exception,
        string $logLevel
    ) {
        $this->innerClient
            ->method('request')
            ->with($requestMethod, $requestPath)
            ->willThrowException($exception);

        try {
            $client = new LoggedClient($this->innerClient, $this->logger);
            $client->get($requestPath);

            $this->fail('The client does not throw the expected exception!');
        } catch (ApiExceptionInterface $e) {
            $expectedRecord = [
                'message' => "[API] Call \"$requestMethod $requestPath\"",
                'context' => [
                    'request'   => [
                        'method' => $requestMethod,
                        'uri'    => $requestPath,
                        'data'   => [],
                        'query'  => [],
                    ],
                    'exception' => $exception,
                ],
            ];

            $this->assertTrue($this->logger->hasRecord($expectedRecord, $logLevel));
        }
    }

    public function exceptionsProvider(): iterable
    {
        yield [
            'method'    => 'GET',
            'path'      => 'path/does/not/exist',
            'exception' => new ResourceNotFoundException(),
            'log_level' => 'warning',
        ];

        yield [
            'method'    => 'GET',
            'path'      => 'path/to/generic/exception',
            'exception' => new ApiException(),
            'log_level' => 'error',
        ];
    }

    public function testFileUploadRequest()
    {
        $requestMethod = 'POST';
        $requestPath = 'upload-file';
        $path = __DIR__ . '/fixtures/spacer.gif';

        $params = [
            'file'    => new SplFileObject($path),
            'title'   => 'Do you remember the spacer.gif?',
            'caption' => 'A spacer.gif was a small and transparent GIF image, used to control the visual layout of HTML elements on a web page.',
        ];

        $result = ['message' => 'OK'];

        $this->innerClient
            ->method('request')
            ->with($requestMethod, $requestPath, $params)
            ->willReturn($result);

        $client = new LoggedClient($this->innerClient, $this->logger);
        $client->post($requestPath, $params);

        $expectedRecord = [
            'message' => "[API] Call \"$requestMethod $requestPath\"",
            'context' => [
                'request' => [
                    'method' => $requestMethod,
                    'uri'    => $requestPath,
                    'data'   => [
                        'file'    => "[FILE] $path",
                        'title'   => 'Do you remember the spacer.gif?',
                        'caption' => 'A spacer.gif was a small and transparent GIF image, used to control the visual layout of HTML elements on a web page.',
                    ],
                    'query'  => [],
                ],
                'result'  => $result,
            ],
        ];

        $this->assertTrue($this->logger->hasRecord($expectedRecord, 'info'));
    }
}
