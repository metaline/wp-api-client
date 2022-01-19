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

trait ClientTrait
{
    abstract public function request(string $method, string $uri, array $data = [], array $query = []): array;

    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $uri, [], $query);
    }

    public function put(string $uri, array $params = []): array
    {
        return $this->request('PUT', $uri, $params);
    }

    public function post(string $uri, array $params = []): array
    {
        return $this->request('POST', $uri, $params);
    }

    public function patch(string $uri, array $params = []): array
    {
        return $this->request('PATCH', $uri, $params);
    }

    public function delete(string $uri, array $query = []): array
    {
        return $this->request('DELETE', $uri, [], $query);
    }
}
