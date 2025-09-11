<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * Modified for UserSpice Security
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\RequestMatcher;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

final class ExpressionRequestMatcher implements RequestMatcherInterface
{
    public function __construct(
        private readonly ExpressionLanguage $language,
        /** MUST be from trusted code/config, never user input */
        private readonly Expression|string $expression,
    ) {}

    public function matches(Request $request): bool
    {
        // REQUIRE: configure these at bootstrap if applicable
        // Request::setTrustedProxies([...], Request::HEADER_X_FORWARDED_ALL);
        // Request::setTrustedHosts(['^example\.com$', '^api\.example\.com$']);

        // Strict, minimal context: NO objects, NO attributes, NO rawurldecode
        $ctx = [
            'method' => $request->getMethod(),           // e.g. 'GET'
            'path'   => $request->getPathInfo(),         // already normalized by Symfony
            'host'   => $request->getHost(),             // safe if Trusted Hosts are set
            'ip'     => $request->getClientIp(),         // safe if Trusted Proxies are set
        ];

        return (bool) $this->language->evaluate($this->expression, $ctx);
    }
}
