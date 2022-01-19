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

use MetaLine\WordPressAPIClient\Exception\ResourceNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use SplFileObject;

final class LoggedClient implements ClientInterface
{
    use ClientTrait;

    private ClientInterface $client;

    private LoggerInterface $logger;

    public function __construct(ClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function request(string $method, $uri, array $data = [], array $query = []): array
    {
        $logRequest = [
            'method' => $method,
            'uri'    => $uri,
            'data'   => $this->prepareDataForLog($data),
            'query'  => $query,
        ];

        try {
            $result = $this->client->request($method, $uri, $data, $query);

            $this->logger->info(sprintf('[API] Call "%s %s"', $method, $uri), [
                'request' => $logRequest,
                'result'  => $result,
            ]);

            return $result;
        } catch (ApiExceptionInterface $e) {
            $logLevel = $e instanceof ResourceNotFoundException ? LogLevel::WARNING : LogLevel::ERROR;

            $this->logger->log($logLevel, sprintf('[API] Call "%s %s"', $method, $uri), [
                'request'   => $logRequest,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    private function prepareDataForLog(array $data): array
    {
        foreach ($data as &$value) {
            if ($value instanceof SplFileObject) {
                $value = "[FILE] {$value->getPathname()}";
            }
        }

        return $data;
    }
}
