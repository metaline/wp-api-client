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

namespace MetaLine\WordPressAPIClient;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Log\LoggerInterface;

final class ClientFactory
{
    private ?LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function createFromWooCommerceCredentials(
        string $customerKey,
        string $customerSecret,
        string $url
    ): ClientInterface {
        $options = [
            'auth'     => [$customerKey, $customerSecret],
            'base_uri' => $this->normalizeUrl($url),
        ];

        $client = new Client(
            $this->createGuzzleClient($options)
        );

        if ($this->logger) {
            return new LoggedClient($client, $this->logger);
        }

        return $client;
    }

    private function createGuzzleClient(array $options): GuzzleClient
    {
        $options['http_errors'] = false;

        return new GuzzleClient($options);
    }

    private function normalizeUrl(string $url): string
    {
        return rtrim($url, '/') . '/';
    }
}
