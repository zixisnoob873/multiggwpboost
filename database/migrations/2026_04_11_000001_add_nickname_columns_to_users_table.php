<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 25)->nullable()->after('last_name');
            $table->string('nickname_normalized', 25)->nullable()->after('nickname');
            $table->index('nickname');
        });

        DB::table('users')
            ->select(['id', 'name', 'first_name', 'last_name', 'email', 'role'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                $reserved = DB::table('users')
                    ->whereNotNull('nickname_normalized')
                    ->pluck('nickname_normalized')
                    ->map(fn ($value) => Str::lower((string) $value))
                    ->filter()
                    ->values()
                    ->all();

                foreach ($users as $user) {
                    $nickname = $this->uniqueNicknameForUser($user, $reserved);

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'nickname' => $nickname,
                            'nickname_normalized' => Str::lower($nickname),
                        ]);

                    $reserved[] = Str::lower($nickname);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('nickname_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nickname_normalized']);
            $table->dropIndex(['nickname']);
            $table->dropColumn(['nickname', 'nickname_normalized']);
        });
    }

    protected function uniqueNicknameForUser(object $user, array $reserved): string
    {
        $sources = [
            $this->compactAlphaNumeric(trim((string) ($user->nickname ?? ''))),
            $this->compactAlphaNumeric(trim((string) ($user->first_name ?? '')).trim((string) ($user->last_name ?? ''))),
            $this->compactAlphaNumeric(trim((string) ($user->name ?? ''))),
            $this->compactAlphaNumeric(Str::before((string) ($user->email ?? ''), '@')),
            $this->compactAlphaNumeric((string) ($user->role ?? 'User')),
            'User'.$user->id,
        ];

        foreach ($sources as $source) {
            $candidate = $this->firstUniqueCandidate($source, $reserved);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return $this->firstUniqueCandidate('User'.$user->id, $reserved) ?? 'User'.$user->id;
    }

    protected function firstUniqueCandidate(string $base, array $reserved): ?string
    {
        $base = substr($base, 0, 25);

        if ($base === '') {
            return null;
        }

        $normalizedBase = Str::lower($base);

        if (! in_array($normalizedBase, $reserved, true)) {
            return $base;
        }

        for ($suffix = 2; $suffix <= 9999; $suffix++) {
            $suffixText = (string) $suffix;
            $trimmedBase = substr($base, 0, max(1, 25 - strlen($suffixText)));
            $candidate = $trimmedBase.$suffixText;
            $normalizedCandidate = Str::lower($candidate);

            if (! in_array($normalizedCandidate, $reserved, true)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function compactAlphaNumeric(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? '';

        return substr($value, 0, 25);
    }
};
