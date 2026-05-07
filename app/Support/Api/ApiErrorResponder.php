<?php

namespace App\Support\Api;

use App\Support\Logging\AppEventLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiErrorResponder
{
    public function __construct(protected AppEventLogger $eventLogger) {}

    public function shouldRenderJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->is('api/*');
    }

    public function render(Request $request, Throwable $exception): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'error_code' => 'validation_failed',
                'errors' => $exception->errors(),
            ], $exception->status);
        }

        $status = $this->statusCode($exception);
        $payload = [
            'success' => false,
            'message' => $this->safeMessage($status),
            'error_code' => $this->errorCode($status),
        ];
        $headers = [];

        if ($status === 429) {
            $retryAfter = (int) (($exception instanceof HttpExceptionInterface ? $exception->getHeaders()['Retry-After'] ?? 0 : 0) ?: 0);

            if ($retryAfter > 0) {
                $payload['retry_after'] = $retryAfter;
                $headers['Retry-After'] = (string) $retryAfter;
            }

            $this->eventLogger->api('api.rate_limited', $request, [
                'status' => $status,
                ...$this->eventLogger->exceptionContext($exception),
            ]);
        } elseif ($status === 401 || $status === 403) {
            $this->eventLogger->security($status === 401 ? 'auth.challenge' : 'security.access_denied', $request, [
                'status' => $status,
                ...$this->eventLogger->exceptionContext($exception),
            ], $status === 401 ? 'info' : 'warning');
        } elseif ($status >= 500) {
            $this->eventLogger->api('api.unhandled_exception', $request, [
                'status' => $status,
                ...$this->eventLogger->exceptionContext($exception),
            ], 'error');
        }

        return response()->json($payload, $status, $headers);
    }

    protected function statusCode(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException => $exception->status,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof NotFoundHttpException => 404,
            $exception instanceof MethodNotAllowedHttpException => 405,
            $exception instanceof TokenMismatchException => 419,
            $exception instanceof ThrottleRequestsException => 429,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }

    protected function safeMessage(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be processed.',
            401 => 'Authentication is required to access this resource.',
            403 => 'You are not authorized to perform this action.',
            404 => 'The requested resource could not be found.',
            405 => 'The requested method is not allowed for this endpoint.',
            419 => 'Your session expired. Refresh the page and try again.',
            422 => 'The request could not be validated.',
            429 => 'Too many requests. Please try again in a moment.',
            default => $status >= 500
                ? 'Something went wrong.'
                : 'The request could not be completed.',
        };
    }

    protected function errorCode(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'authentication_required',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            419 => 'session_expired',
            422 => 'unprocessable_request',
            429 => 'rate_limited',
            default => $status >= 500 ? 'server_error' : 'request_failed',
        };
    }
}
