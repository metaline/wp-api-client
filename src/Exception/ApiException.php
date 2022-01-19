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

namespace MetaLine\WordPressAPIClient\Exception;

use MetaLine\WordPressAPIClient\ApiExceptionInterface;
use RuntimeException;

final class ApiException extends RuntimeException implements ApiExceptionInterface
{
}
