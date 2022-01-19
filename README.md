# WP API Client

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

## License

This project is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.
