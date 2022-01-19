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

            // Nel caso venga restituita una WP_Error
            if (isset($result['code'])) {
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
        } catch (ConnectException $e) {
            --$retries;

            if (0 === $retries) {
                throw $e;
            }

            return $this->sendRequest($method, $uri, $data, $retries);
        }
    }
}
