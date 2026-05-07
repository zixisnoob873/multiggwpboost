<?php

namespace App\Http\Middleware;

use App\Support\Logging\AppEventLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BindRequestLoggingContext
{
    public function __construct(protected AppEventLogger $eventLogger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = trim((string) $request->headers->get('X-Request-Id', ''));

        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('request_id', $requestId);
        $this->eventLogger->shareRequestContext($request, $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
