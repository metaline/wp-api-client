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

use GuzzleHttp\ClientInterface as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use MetaLine\WordPressAPIClient\Exception\ApiException;
use MetaLine\WordPressAPIClient\Exception\ResourceNotFoundException;
use Psr\Http\Message\ResponseInterface;
use SplFileObject;

final class Client implements ClientInterface
{
    use ClientTrait;

    private GuzzleClient $client;

    public function __construct(GuzzleClient $client)
    {
        $this->client = $client;
    }

    public function request(string $method, string $uri, array $data = [], array $query = []): array
    {
        $method = $this->normalizeMethod($method);

        if (false !== ($pos = strpos($uri, '?'))) {
            $uriQuery = [];
            parse_str(substr($uri, $pos + 1), $uriQuery);

            $uri = substr($uri, 0, $pos);
            $query = array_merge($uriQuery, $query);
        }

        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        try {
            $response = $this->sendRequest($method, $uri, $data);
            $body = $response->getBody()->getContents();

            if (404 === $response->getStatusCode()) {
                throw new ResourceNotFoundException(
                    sprintf('Resource %s %s does not exist, body = %s', $method, $uri, $body)
                );
            }

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new ApiException(sprintf(
                    'Unexpected status code "%s" from request %s %s, body = %s',
                    $response->getStatusCode(), $method, $uri, $body
                ));
            }

            $result = json_decode($body, true);

            if (!is_array($result)) {
                throw new ApiException(
                    sprintf('Invalid result from request %s %s, response body: %s', $method, $uri, $body)
                );
            }

            if ($this->isWordPressError($result)) {
                throw new ApiException(
                    sprintf('Error from request %s %s, response body: %s', $method, $uri, $body)
                );
            }

            return $result;
        } catch (GuzzleException $e) {
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Converts an HTTP method to uppercase, as the protocol requires.
     *
     * Only the ASCII letters are converted, the same way guzzlehttp/psr7 does it:
     * strtoupper() honors LC_CTYPE before PHP 8.2, and this library still supports
     * that range.
     */
    private function normalizeMethod(string $method): string
    {
        return strtr($method, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    /**
     * Checks whether the result is a WP_Error serialized by the REST API.
     *
     * A WP_Error always carries a code, a message and a status inside the data key.
     * Checking the code alone is not enough: several legitimate resources expose a
     * code of their own (coupons, countries, currencies, …).
     */
    private function isWordPressError(array $result): bool
    {
        if (!isset($result['code'], $result['message'], $result['data']['status'])) {
            return false;
        }

        return is_string($result['code'])
            && is_string($result['message'])
            && is_numeric($result['data']['status']);
    }

    /**
     * @throws GuzzleException
     */
    private function sendRequest(string $method, string $uri, array $data = [], int $retries = 5): ResponseInterface
    {
        $options = [];

        if (!empty($data)) {
            $isMultipart = false;
            foreach ($data as $value) {
                if ($value instanceof SplFileObject) {
                    $isMultipart = true;
                    break;
                }
            }

            if ($isMultipart) {
                $options['multipart'] = [];
                foreach ($data as $key => $value) {
                    if ($value instanceof SplFileObject) {
                        $options['multipart'][] = [
                            'name'     => 'file',
                            'contents' => file_get_contents($value->getPathname()),
                            'filename' => $value->getFilename(),
                        ];
                    } else {
                        $options['multipart'][] = [
                            'name'     => $key,
                            'contents' => $value,
                        ];
                    }
                }
            } else {
                $options['json'] = $data;
            }
        }

        try {
            return $this->client->request($method, $uri, $options);
        } catch (ConnectException|ServerException $e) {
            --$retries;

            if (0 === $retries) {
                throw $e;
            }

            return $this->sendRequest($method, $uri, $data, $retries);
        }
    }
}
