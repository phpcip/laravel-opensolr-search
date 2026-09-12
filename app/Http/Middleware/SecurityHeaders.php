<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers on every response.
 *
 * The Content Security Policy allows scripts and styles from this origin only. Result
 * thumbnails are hosted by the indexed sites, so images may load from any https host.
 * While the Vite dev server is running its origin is added, because hot module reloading
 * is served from there and injects styles inline.
 */
class SecurityHeaders
{
    /**
     * Attach the headers to the outgoing response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }

    /**
     * Build the policy string, widened for the Vite dev server only while it is running.
     */
    protected function policy(): string
    {
        $script = "'self'";
        $style = "'self'";
        $connect = "'self'";

        if (Vite::isRunningHot()) {
            $hot = rtrim(trim((string) file_get_contents(public_path('hot'))), '/');
            $socket = preg_replace('#^http#', 'ws', $hot);
            $script .= " {$hot}";
            $style .= " {$hot} 'unsafe-inline'";
            $connect .= " {$hot} {$socket}";
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src {$script}",
            "style-src {$style}",
            "img-src 'self' https: data:",
            "font-src 'self'",
            "connect-src {$connect}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
