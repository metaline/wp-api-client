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
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use SplFileObject;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;
use WMDE\PsrLogTestDoubles\LogCall;
use WMDE\PsrLogTestDoubles\LoggerSpy;

class LoggedClientTest extends TestCase
{
    use ClientTrait;

    /**
     * @var LegacyLoggerSpy|LoggerSpy
     */
    private $logger;

    private ClientInterface $innerClient;

    protected function setUp(): void
    {
        $this->logger = $this->createLoggerSpy();
        $this->innerClient = $this->createMock(ClientInterface::class);
    }

    /**
     * @return LegacyLoggerSpy|LoggerSpy
     */
    private function createLoggerSpy()
    {
        if (!class_exists(LegacyLoggerSpy::class)) {
            return new LoggerSpy();
        }

        $reflection = new \ReflectionClass(LoggerInterface::class);
        $messageParameter = $reflection->getMethod('log')->getParameters()[1];

        if ($messageParameter->getType()) {
            foreach ($messageParameter->getType()->getTypes() as $type) {
                if ('Stringable' === $type->getName()) {
                    return new LoggerSpy();
                }
            }
        }

        return new LegacyLoggerSpy();
    }

    /**
     * @dataProvider logLevelsProvider
     */
    public function testSuccessfulRequestIsLogged($logLevel)
    {
        $requestMethod = 'GET';
        $requestPath = 'success';
        $result = ['message' => 'OK'];

        $this->innerClient
            ->expects($this->once())
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

        $this->assertLogRecord(
            $this->logger->getFirstLogCall(),
            $logLevel,
            "[API] Call \"$requestMethod $requestPath\"",
            [
                'request' => [
                    'method' => $requestMethod,
                    'uri'    => $requestPath,
                    'data'   => [],
                    'query'  => [],
                ],
                'result'  => $result,
            ]
        );
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
            ->expects($this->once())
            ->method('request')
            ->with($requestMethod, $requestPath)
            ->willThrowException($exception);

        try {
            $client = new LoggedClient($this->innerClient, $this->logger);
            $client->get($requestPath);

            $this->fail('The client does not throw the expected exception!');
        } catch (ApiExceptionInterface $e) {
            $this->assertLogRecord(
                $this->logger->getFirstLogCall(),
                $logLevel,
                "[API] Call \"$requestMethod $requestPath\"",
                [
                    'request'   => [
                        'method' => $requestMethod,
                        'uri'    => $requestPath,
                        'data'   => [],
                        'query'  => [],
                    ],
                    'exception' => $exception,
                ]
            );
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
            ->expects($this->once())
            ->method('request')
            ->with($requestMethod, $requestPath, $params)
            ->willReturn($result);

        $client = new LoggedClient($this->innerClient, $this->logger);
        $client->post($requestPath, $params);

        $this->assertLogRecord(
            $this->logger->getFirstLogCall(),
            LogLevel::INFO,
            "[API] Call \"$requestMethod $requestPath\"",
            [
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
            ]
        );
    }

    private function assertLogRecord(
        LogCall $logCall,
        string $expectedLevel,
        string $expectedMessage,
        array $expectedContext
    ): void {
        $this->assertSame($expectedLevel, $logCall->getLevel());
        $this->assertSame($expectedMessage, $logCall->getMessage());
        $this->assertEquals($expectedContext, $logCall->getContext());
    }
}
