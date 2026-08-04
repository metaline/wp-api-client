# WP API Client

![Test library](https://github.com/metaline/wp-api-client/actions/workflows/test-library.yml/badge.svg)

A PHP client for the WordPress REST API, based on [Guzzle](https://guzzlephp.org/).

## Installation

The recommended way to install this library is through [Composer](https://getcomposer.org/).

```
composer require metaline/wp-api-client
```

## Documentation

### Create the client

You can create the client instance through the `ClientFactory`. At the moment, only WooCommerce credentials are supported:

```php
use MetaLine\WordPressAPIClient\ClientFactory;

$factory = new ClientFactory();
$client = $factory->createFromWooCommerceCredentials(
    $customerKey,
    $customerSecret,
    $apiUrl
);
```

The factory accepts an optional array of options, to customize the timeouts of the requests:

```php
$client = $factory->createFromWooCommerceCredentials(
    $customerKey,
    $customerSecret,
    $apiUrl,
    [
        'timeout'         => 60,
        'connect_timeout' => 5,
    ]
);
```

| Option            | Default | Description                                                |
|-------------------|---------|------------------------------------------------------------|
| `timeout`         | `120`   | Maximum number of seconds to wait for the whole request.   |
| `connect_timeout` | `10`    | Maximum number of seconds to wait while trying to connect. |

Both options are passed to Guzzle, so you can refer to the [Guzzle documentation](https://docs.guzzlephp.org/en/stable/request-options.html) for more details.

If you need to access to the WordPress REST API through the WooCommerce API credentials, you need this hook in YOUR installation of WordPress:

```php
add_filter('woocommerce_rest_is_request_to_rest_api', function ($enabled) {
	if (!$enabled) {
        $rest_prefix = trailingslashit(rest_get_url_prefix());
        $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
        $enabled = false !== strpos($request_uri, $rest_prefix . 'wp/');
    }

    return $enabled;
});
```

### Fetch data from REST API

Through the client instance you can fetch data from the WordPress REST API. For example:

```php
$customers = $client->request('GET', 'wc/v3/customers');
```

Please, refer to the [WordPress](https://developer.wordpress.org/rest-api/reference/) and [WooCommerce](https://woocommerce.github.io/woocommerce-rest-api-docs/) REST API documentation, for all available methods.

### Helper methods

The client has five helper methods, one for each REST verb: `get()`, `post()`, `put()`, `patch()` and `delete()`.

| HTTP verb | Helper method                   |
|-----------|---------------------------------|
| GET       | `$client->get($uri, $query)`    |
| POST      | `$client->post($uri, $params)`  |
| PUT       | `$client->put($uri, $params)`   |
| PATCH     | `$client->patch($uri, $params)` |
| DELETE    | `$client->delete($uri, $query)` |

- `$uri` is the endpoint of the call;
- `$query` is an array of variables to put in query string;
- `$params` is an array of data to put in the body request;

### Upload files

A special case is the [media](https://developer.wordpress.org/rest-api/reference/media/) endpoints, which allow us to upload a file:

```php
$data = [
	'file' => new SplFileObject('/path/to/file.zip'),
];

$client->post('wp/v2/media', $data);
```

### Retries

When a request fails at the transport level — the host cannot be resolved, the connection is refused or the server times out — the client sends it again, up to 5 times in total.

Only the methods that are [idempotent by definition](https://www.rfc-editor.org/rfc/rfc9110#section-9.2.2) are retried: `GET`, `HEAD`, `OPTIONS`, `TRACE`, `PUT` and `DELETE`. A `POST` or a `PATCH` is sent once and never repeated, because a transport error does not tell us whether the server has already processed the request: a timeout on `POST wc/v3/orders` may well leave an order behind, and retrying it would create a second one.

## License

This project is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.
