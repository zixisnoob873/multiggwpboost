<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_checkouts', function (Blueprint $table): void {
            $table->longText('request_data')->change();
            $table->longText('order_payload')->change();
            $table->longText('base_order_payload')->nullable()->change();
            $table->longText('metadata')->nullable()->change();
        });

        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->longText('payload')->nullable()->change();
        });

        $this->encryptColumnValues('pending_checkouts', [
            'request_data',
            'order_payload',
            'base_order_payload',
            'metadata',
        ]);
        $this->encryptColumnValues('payment_webhook_events', ['payload']);
    }

    public function down(): void
    {
        $this->decryptColumnValues('pending_checkouts', [
            'request_data',
            'order_payload',
            'base_order_payload',
            'metadata',
        ]);
        $this->decryptColumnValues('payment_webhook_events', ['payload']);

        Schema::table('pending_checkouts', function (Blueprint $table): void {
            $table->json('request_data')->change();
            $table->json('order_payload')->change();
            $table->json('base_order_payload')->nullable()->change();
            $table->json('metadata')->nullable()->change();
        });

        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->json('payload')->nullable()->change();
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function encryptColumnValues(string $table, array $columns): void
    {
        DB::table($table)
            ->select(['id', ...$columns])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $updates[$column] = $this->encryptLegacyValue($row->{$column} ?? null);
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update($updates);
                }
            });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function decryptColumnValues(string $table, array $columns): void
    {
        DB::table($table)
            ->select(['id', ...$columns])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $updates[$column] = $this->decryptLegacyValue($row->{$column} ?? null);
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update($updates);
                }
            });
    }

    private function encryptLegacyValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $serialized = is_string($value)
            ? $value
            : json_encode($value, JSON_THROW_ON_ERROR);

        if ($this->isEncrypted($serialized)) {
            return $serialized;
        }

        return Crypt::encryptString($serialized);
    }

    private function decryptLegacyValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $serialized = (string) $value;

        try {
            return Crypt::decryptString($serialized);
        } catch (Throwable) {
            return $serialized;
        }
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
