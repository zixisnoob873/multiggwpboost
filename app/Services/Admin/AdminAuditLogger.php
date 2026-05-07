<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Support\Logging\AppEventLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminAuditLogger
{
    public function __construct(
        protected AppEventLogger $eventLogger,
    ) {}

    public function log(
        string $module,
        string $action,
        ?User $actor = null,
        Model|string|null $subject = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        $subjectPayload = $this->subjectPayload($subject);
        $metadata = $this->sanitize($metadata);

        AdminAuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->role,
            'module' => $module,
            'action' => $action,
            'subject_type' => $subjectPayload['type'],
            'subject_id' => $subjectPayload['id'],
            'subject_label' => $subjectPayload['label'],
            'route_name' => $request?->route()?->getName(),
            'method' => $request?->method(),
            'ip_address' => $request?->ip(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        if ($request instanceof Request) {
            $this->eventLogger->admin(
                'admin.audit.'.$action,
                $request,
                $actor,
                array_filter([
                    'module' => $module,
                    'subject_type' => $subjectPayload['type'],
                    'subject_id' => $subjectPayload['id'],
                    'subject_label' => $subjectPayload['label'],
                    'metadata' => $metadata !== [] ? $metadata : null,
                ], static fn (mixed $value): bool => $value !== null && $value !== [])
            );
        }
    }

    protected function subjectPayload(Model|string|null $subject): array
    {
        if ($subject instanceof Model) {
            return [
                'type' => $subject::class,
                'id' => $subject->getKey(),
                'label' => $this->subjectLabel($subject),
            ];
        }

        if (is_string($subject) && trim($subject) !== '') {
            return [
                'type' => 'string',
                'id' => null,
                'label' => Str::limit(trim($subject), 255, ''),
            ];
        }

        return [
            'type' => null,
            'id' => null,
            'label' => null,
        ];
    }

    protected function subjectLabel(Model $subject): ?string
    {
        foreach (['title', 'name', 'code', 'order_number', 'key', 'email'] as $attribute) {
            $value = trim((string) data_get($subject, $attribute));

            if ($value !== '') {
                return Str::limit($value, 255, '');
            }
        }

        return Str::limit(class_basename($subject).' #'.$subject->getKey(), 255, '');
    }

    protected function sanitize(array $metadata): array
    {
        $redactedKeys = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'signature',
            'authorization',
        ];

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);

                continue;
            }

            if (in_array($normalizedKey, $redactedKeys, true) || Str::endsWith($normalizedKey, ['_token', '_secret', '_password'])) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
