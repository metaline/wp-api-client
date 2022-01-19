<?php

/*
 * This file is part of the WP API Client library.
 *
 * (c) Meta Line Srl
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MetaLine\WordPressAPIClient\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use MetaLine\WordPressAPIClient\Client;

trait ClientTrait
{
    /**
     * @dataProvider requestWithQueryStringProvider
     */
    public function testHelperMethodWithQueryString(string $method, array $query, string $expectedUri)
    {
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')
            ->with(strtoupper($method), $expectedUri, [])
            ->willReturn(new Response(200, [], '{"message":"OK"}'));

        $client = new Client($guzzle);
        $result = $client->$method('path', $query);

        $this->assertEquals(['message' => 'OK'], $result);
    }

    public function requestWithQueryStringProvider(): iterable
    {
        foreach (['get', 'delete'] as $method) {
            yield [
                $method,
                [],
                'path',
            ];

            yield [
                $method,
                ['key' => 'value'],
                'path?key=value',
            ];
        }
    }

    /**
     * @dataProvider requestWithBodyProvider
     */
    public function testHelperMethodWithBody(string $method, array $data, array $expectedOptions)
    {
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')
            ->with(strtoupper($method), 'path', $expectedOptions)
            ->willReturn(new Response(200, [], '{"message":"OK"}'));

        $client = new Client($guzzle);
        $result = $client->$method('path', $data);

        $this->assertEquals(['message' => 'OK'], $result);
    }

    public function requestWithBodyProvider(): iterable
    {
        foreach (['post', 'put', 'patch'] as $method) {
            yield [
                $method,
                [],
                [],
            ];

            yield [
                $method,
                ['key' => 'value'],
                ['json' => ['key' => 'value']],
            ];
        }
    }
}
