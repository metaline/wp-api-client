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

use MetaLine\WordPressAPIClient\Client;
use MetaLine\WordPressAPIClient\ClientFactory;
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
}
