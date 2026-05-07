<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HealthReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ready',
        ]);
    }

    public function internal(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseCheck(),
            'cache' => $this->cacheCheck(),
            'storage' => $this->storageCheck(),
            'public_storage_link' => $this->publicStorageLinkCheck(),
        ];

        $healthy = collect($checks)->every(fn (array $check) => $check['ok'] === true);
        $status = $healthy ? 200 : 503;

        if (! $healthy) {
            Log::warning('Readiness check failed.', [
                'checks' => $checks,
            ]);
        }

        return response()->json([
            'status' => $healthy ? 'ready' : 'degraded',
            'checkedAt' => now()->toIso8601String(),
            'checks' => $checks,
        ], $status);
    }

    protected function databaseCheck(): array
    {
        try {
            DB::select('SELECT 1');

            return ['ok' => true];
        } catch (\Throwable $exception) {
            return $this->failedCheck('database', $exception);
        }
    }

    protected function cacheCheck(): array
    {
        $key = 'health:cache:'.Str::uuid();

        try {
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return ['ok' => $value === 'ok'];
        } catch (\Throwable $exception) {
            return $this->failedCheck('cache', $exception);
        }
    }

    protected function storageCheck(): array
    {
        $disk = (string) config('filesystems.default', 'public');
        $path = 'healthchecks/'.Str::uuid().'.txt';

        try {
            Storage::disk($disk)->put($path, 'ok');
            $ok = Storage::disk($disk)->exists($path);
            Storage::disk($disk)->delete($path);

            return ['ok' => $ok, 'disk' => $disk];
        } catch (\Throwable $exception) {
            return [
                ...$this->failedCheck('storage', $exception),
                'disk' => $disk,
            ];
        }
    }

    protected function publicStorageLinkCheck(): array
    {
        if (app()->runningUnitTests()) {
            return ['ok' => true];
        }

        $publicStoragePath = public_path('storage');
        $publicStorageTarget = storage_path('app/public');

        $ok = file_exists($publicStoragePath)
            && realpath($publicStoragePath) === realpath($publicStorageTarget);

        $payload = [
            'ok' => $ok,
        ];

        if ((bool) config('app.debug')) {
            $payload['path'] = $publicStoragePath;
            $payload['target'] = $publicStorageTarget;
        }

        return $payload;
    }

    protected function failedCheck(string $component, \Throwable $exception): array
    {
        if ((bool) config('app.debug')) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ];
        }

        return [
            'ok' => false,
            'message' => ucfirst($component).' check failed.',
        ];
    }
}
