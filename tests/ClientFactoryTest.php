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

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use MetaLine\WordPressAPIClient\Client;
use MetaLine\WordPressAPIClient\ClientFactory;
use MetaLine\WordPressAPIClient\ClientInterface;
use MetaLine\WordPressAPIClient\LoggedClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ClientFactoryTest extends TestCase
{
    public function testWooCommerceFactory()
    {
        $factory = new ClientFactory();

        $client = $factory->createFromWooCommerceCredentials(
            'customer-key',
            'customer-secret',
            'https://example.com/wp-json/'
        );

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testWooCommerceFactoryWithLogger()
    {
        $factory = new ClientFactory($this->createMock(LoggerInterface::class));

        $client = $factory->createFromWooCommerceCredentials(
            'customer-key',
            'customer-secret',
            'https://example.com/wp-json/'
        );

        $this->assertInstanceOf(LoggedClient::class, $client);
    }

    public function testWooCommerceFactorySetsCredentialsAndDisablesHttpErrors()
    {
        $factory = new ClientFactory();

        $client = $factory->createFromWooCommerceCredentials(
            'customer-key',
            'customer-secret',
            'https://example.com/wp-json/'
        );

        $config = $this->readGuzzleConfig($client);

        $this->assertSame(['customer-key', 'customer-secret'], $config['auth']);
        $this->assertFalse($config['http_errors']);
    }

    public function testWooCommerceFactoryUsesDefaultTimeouts()
    {
        $factory = new ClientFactory();

        $client = $factory->createFromWooCommerceCredentials(
            'customer-key',
            'customer-secret',
            'https://example.com/wp-json/'
        );

        $config = $this->readGuzzleConfig($client);

        $this->assertSame(120, $config['timeout']);
        $this->assertSame(10, $config['connect_timeout']);
    }

    /**
     * @dataProvider optionsProvider
     */
    public function testWooCommerceFactoryUsesGivenTimeouts(
        array $options,
        int $expectedTimeout,
        int $expectedConnectTimeout
    ) {
        $factory = new ClientFactory();

        $client = $factory->createFromWooCommerceCredentials(
            'customer-key',
            'customer-secret',
            'https://example.com/wp-json/',
            $options
        );

        $config = $this->readGuzzleConfig($client);

        $this->assertSame($expectedTimeout, $config['timeout']);
        $this->assertSame($expectedConnectTimeout, $config['connect_timeout']);
    }

    public function optionsProvider(): iterable
    {
        yield 'no option' => [
            [],
            120,
            10,
        ];

        yield 'both options' => [
            ['timeout' => 30, 'connect_timeout' => 5],
            30,
            5,
        ];

        yield 'only timeout' => [
            ['timeout' => 30],
            30,
            10,
        ];

        yield 'only connect_timeout' => [
            ['connect_timeout' => 5],
            120,
            5,
        ];
    }

    public function testWooCommerceFactoryIgnoresUnknownOptions()
    {
        $factory = new ClientFactory();

        $client = $factory->createFromWooCommerceCredentials(
            'customer-key',
            'customer-secret',
            'https://example.com/wp-json/',
            ['foo' => 'bar']
        );

        $config = $this->readGuzzleConfig($client);

        $this->assertArrayNotHasKey('foo', $config);
    }

    /**
     * @dataProvider urlProvider
     */
    public function testWooCommerceFactoryNormalizesTheUrl(string $url, string $expectedBaseUri)
    {
        $factory = new ClientFactory();

        $client = $factory->createFromWooCommerceCredentials('customer-key', 'customer-secret', $url);

        $config = $this->readGuzzleConfig($client);

        $this->assertSame($expectedBaseUri, (string) $config['base_uri']);
    }

    public function urlProvider(): iterable
    {
        yield 'with trailing slash' => [
            'https://example.com/wp-json/',
            'https://example.com/wp-json/',
        ];

        yield 'without trailing slash' => [
            'https://example.com/wp-json',
            'https://example.com/wp-json/',
        ];

        yield 'with multiple trailing slashes' => [
            'https://example.com/wp-json///',
            'https://example.com/wp-json/',
        ];
    }

    private function readGuzzleConfig(ClientInterface $client): array
    {
        $this->assertInstanceOf(
            Client::class,
            $client,
            'The factory no longer returns a Client, this helper must be updated.'
        );

        $readGuzzle = Closure::bind(
            function () {
                return $this->client;
            },
            $client,
            Client::class
        );

        $guzzle = $readGuzzle();

        $this->assertInstanceOf(GuzzleClient::class, $guzzle);

        return $guzzle->getConfig();
    }
}
