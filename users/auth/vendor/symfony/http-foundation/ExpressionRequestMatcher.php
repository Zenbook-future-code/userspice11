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

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\RequestMatcher\ExpressionRequestMatcher as NewExpressionRequestMatcher;

trigger_deprecation('symfony/http-foundation', '6.2', 'The "%s" class is deprecated, use "%s" instead.', ExpressionRequestMatcher::class, NewExpressionRequestMatcher::class);

/**
 * ExpressionRequestMatcher uses an expression to match a Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.2, use "Symfony\Component\HttpFoundation\RequestMatcher\ExpressionRequestMatcher" instead
 */
class ExpressionRequestMatcher extends RequestMatcher
{
    /**
     * Set to true (e.g., in a bootstrap file) ONLY if you must temporarily
     * preserve the old behavior that exposed $request and $attributes to the
     * expression context. Default is hardened (false).
     *
     * example:
     *   if (!defined('ALLOW_FULL_REQUEST_IN_EXPRESSIONS')) {
     *       define('ALLOW_FULL_REQUEST_IN_EXPRESSIONS', false);
     *   }
     */
    private const ALLOW_FULL_REQUEST_IN_EXPRESSIONS_DEFAULT = false;

    private ExpressionLanguage $language;
    private Expression|string $expression;

    /**
     * @return void
     */
    public function setExpression(ExpressionLanguage $language, Expression|string $expression)
    {
        // Keep the setter for BC, but treat the expression as code from trusted config.
        $this->language = $language;
        $this->expression = $expression;
    }

    public function matches(Request $request): bool
    {
        if (!isset($this->language)) {
            throw new \LogicException('Unable to match the request as the expression language is not available. Try running "composer require symfony/expression-language".');
        }

        // Hardened, minimal context (no objects; no rawurldecode; scalars only).
        // NOTE: getHost() / getClientIp() are only trustworthy if Trusted Hosts/Proxies are configured at bootstrap.
        $context = [
            'method' => $request->getMethod(),
            'path'   => $request->getPathInfo(),   // do NOT rawurldecode here
            'host'   => $request->getHost(),
            'ip'     => $request->getClientIp(),
        ];


        // Evaluate expression and AND with parent matcher (preserves previous chaining behavior).
        return (bool) $this->language->evaluate($this->expression, $context)
            && parent::matches($request);
    }
}
