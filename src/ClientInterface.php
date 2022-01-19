<?php

/*
 * This file is part of the WP API Client library.
 *
 * (c) Meta Line Srl
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MetaLine\WordPressAPIClient;

interface ClientInterface
{
    /**
     * @throws ApiExceptionInterface
     */
    public function get(string $uri, array $query = []): array;

    /**
     * @throws ApiExceptionInterface
     */
    public function put(string $uri, array $params = []): array;

    /**
     * @throws ApiExceptionInterface
     */
    public function post(string $uri, array $params = []): array;

    /**
     * @throws ApiExceptionInterface
     */
    public function patch(string $uri, array $params = []): array;

    /**
     * @throws ApiExceptionInterface
     */
    public function delete(string $uri, array $query = []): array;

    /**
     * @throws ApiExceptionInterface
     */
    public function request(string $method, string $uri, array $data = [], array $query = []): array;
}
