<?php

namespace App\Support\Logging;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppEventLogger
{
    /**
     * @var array<int, string>
     */
    protected array $redactedKeys = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'secret',
        'signature',
        'authorization',
        'stripe_signature',
        'webhook_secret',
        'checkout_token',
        'session_id',
        'stripe_session_id',
    ];

    public function shareRequestContext(Request $request, ?string $requestId = null): void
    {
        Log::withContext($this->sanitizeContext(
            $this->requestContext($request, $requestId)
        ));
    }

    public function auth(string $event, Request $request, ?User $actor = null, array $context = [], string $level = 'info'): void
    {
        $this->write(
            'activity',
            $level,
            $event,
            array_merge(
                $this->requestContext($request),
                $this->actorContext($actor ?? $request->user()),
                $context,
            ),
        );
    }

    public function admin(string $event, Request $request, ?User $actor = null, array $context = [], string $level = 'info'): void
    {
        $this->write(
            'activity',
            $level,
            $event,
            array_merge(
                $this->requestContext($request),
                $this->actorContext($actor ?? $request->user()),
                $context,
            ),
        );
    }

    public function order(string $event, ?Order $order = null, ?User $actor = null, array $context = [], ?Request $request = null, string $level = 'info'): void
    {
        $this->write(
            'activity',
            $level,
            $event,
            array_merge(
                $request ? $this->requestContext($request) : [],
                $this->actorContext($actor),
                $this->orderContext($order),
                $context,
            ),
        );
    }

    public function payment(string $event, array $context = [], string $level = 'info'): void
    {
        $this->write('payments', $level, $event, $context);
    }

    public function security(string $event, ?Request $request = null, array $context = [], string $level = 'warning'): void
    {
        $this->write(
            'security',
            $level,
            $event,
            array_merge(
                $request ? $this->requestContext($request) : [],
                $request ? $this->actorContext($request->user()) : [],
                $context,
            ),
        );
    }

    public function api(string $event, Request $request, array $context = [], string $level = 'warning'): void
    {
        $this->write(
            'security',
            $level,
            $event,
            array_merge(
                $this->requestContext($request),
                $this->actorContext($request->user()),
                $context,
            ),
        );
    }

    public function exceptionContext(Throwable $exception): array
    {
        return [
            'exception' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function write(string $channel, string $level, string $event, array $context = []): void
    {
        $logger = Log::channel($channel);
        $method = method_exists($logger, $level) ? $level : 'info';

        $logger->{$method}($event, $this->sanitizeContext($context));
    }

    protected function requestContext(Request $request, ?string $requestId = null): array
    {
        $route = $request->route();
        $userAgent = trim(substr((string) $request->userAgent(), 0, 255));

        return array_filter([
            'request_id' => $requestId ?? $request->attributes->get('request_id'),
            'route_name' => $route?->getName(),
            'path' => '/'.ltrim($request->path(), '/'),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $userAgent !== '' ? $userAgent : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function actorContext(?User $actor): array
    {
        if (! $actor instanceof User) {
            return [];
        }

        return [
            'actor_id' => $actor->getKey(),
            'actor_role' => $actor->role,
        ];
    }

    protected function orderContext(?Order $order): array
    {
        if (! $order instanceof Order) {
            return [];
        }

        return [
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'order_status' => $order->status,
            'payment_status' => $order->payment_status,
        ];
    }

    /**
     * @param  array<string|int, mixed>  $context
     * @return array<string|int, mixed>
     */
    protected function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower($key) : (string) $key;

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);

                continue;
            }

            if ($value instanceof Throwable) {
                $sanitized[$key] = [
                    'exception' => $value::class,
                    'exception_message' => $value->getMessage(),
                ];

                continue;
            }

            if ($this->shouldRedact($normalizedKey)) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    protected function shouldRedact(string $key): bool
    {
        foreach ($this->redactedKeys as $needle) {
            if ($key === $needle || str_ends_with($key, '_'.$needle)) {
                return true;
            }
        }

        return false;
    }
}
