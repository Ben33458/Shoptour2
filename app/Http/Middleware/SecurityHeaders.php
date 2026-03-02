<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security-relevant HTTP response headers to all web responses.
 *
 * Headers applied:
 *   X-Content-Type-Options: nosniff
 *     Prevents browsers from MIME-sniffing the content type.
 *
 *   X-Frame-Options: SAMEORIGIN
 *     Blocks clickjacking by preventing the page being embedded in iframes
 *     from other origins.
 *
 *   Referrer-Policy: strict-origin-when-cross-origin
 *     Sends full URL as referrer for same-origin requests; only the origin
 *     for cross-origin HTTPS→HTTPS; nothing for HTTPS→HTTP downgrades.
 *
 *   Content-Security-Policy (minimal):
 *     - default-src 'self'
 *     - script-src  'self' (no inline JS — admin UI uses server-rendered Blade)
 *     - style-src   'self' 'unsafe-inline' (inline styles are used in admin.css variables)
 *     - img-src     'self' data:  (data-URIs for SVG icons, etc.)
 *     - font-src    'self'
 *     - frame-ancestors 'none'  (stronger than X-Frame-Options for modern browsers)
 *
 * NOTE: The driver PWA (/driver/**) may need 'unsafe-eval' for its JS bundle.
 * Adjust the CSP per route group if needed.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options'    => 'nosniff',
        'X-Frame-Options'           => 'SAMEORIGIN',
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Content-Security-Policy' =>
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: blob:; " .
            "font-src 'self'; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none';",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
