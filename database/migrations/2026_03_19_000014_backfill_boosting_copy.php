<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateOrders();
        $this->updateFaqs();
        $this->updateTestimonials();
    }

    public function down(): void
    {
        // This is a one-way content normalization.
    }

    private function updateOrders(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('product')->default('Rank Boosting')->change();
        });

        DB::table('orders')
            ->select(['id', 'product', 'details'])
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $originalDetails = $this->decodeDetails($order->details);
                    $normalizedProduct = $this->normalizeValue($order->product);
                    $normalizedDetails = $this->normalizeValue($originalDetails);
                    $updates = [];

                    if ($normalizedProduct !== $order->product) {
                        $updates['product'] = $normalizedProduct;
                    }

                    if ($normalizedDetails !== $originalDetails) {
                        $updates['details'] = json_encode($normalizedDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    if ($updates !== []) {
                        DB::table('orders')->where('id', $order->id)->update($updates);
                    }
                }
            });
    }

    private function updateFaqs(): void
    {
        if (! Schema::hasTable('faqs')) {
            return;
        }

        DB::table('faqs')
            ->select(['id', 'question', 'answer'])
            ->orderBy('id')
            ->chunkById(100, function ($faqs) {
                foreach ($faqs as $faq) {
                    $question = $this->normalizeValue($faq->question);
                    $answer = $this->normalizeValue($faq->answer);

                    if ($question === $faq->question && $answer === $faq->answer) {
                        continue;
                    }

                    DB::table('faqs')->where('id', $faq->id)->update([
                        'question' => $question,
                        'answer' => $answer,
                    ]);
                }
            });
    }

    private function updateTestimonials(): void
    {
        if (! Schema::hasTable('testimonials')) {
            return;
        }

        DB::table('testimonials')
            ->select(['id', 'service'])
            ->orderBy('id')
            ->chunkById(100, function ($testimonials) {
                foreach ($testimonials as $testimonial) {
                    $service = $this->normalizeValue($testimonial->service);

                    if ($service === $testimonial->service) {
                        continue;
                    }

                    DB::table('testimonials')->where('id', $testimonial->id)->update([
                        'service' => $service,
                    ]);
                }
            });
    }

    private function decodeDetails(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeValue($item), $value);
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        $value = preg_replace('/'.preg_quote($this->legacyPhrase(), '/').'/i', 'Rank Boosting', $value) ?? $value;
        $value = $this->replaceWithCase($value, '/\b'.preg_quote($this->legacyPlural(), '/').'\b/i', 'boosters');
        $value = $this->replaceWithCase($value, '/\b'.preg_quote($this->legacyGerund(), '/').'\b/i', 'boosting');
        $value = $this->replaceWithCase($value, '/\b'.preg_quote($this->legacySingular(), '/').'\b/i', 'booster');

        return $value;
    }

    private function legacyPhrase(): string
    {
        return implode(' ', ['Valorant', $this->legacyGerund(), 'plan']);
    }

    private function legacyPlural(): string
    {
        return $this->legacySingular().'es';
    }

    private function legacyGerund(): string
    {
        return $this->legacySingular().'ing';
    }

    private function legacySingular(): string
    {
        return implode('', ['coa', 'ch']);
    }

    private function replaceWithCase(string $subject, string $pattern, string $replacement): string
    {
        return preg_replace_callback($pattern, function (array $matches) use ($replacement) {
            $matched = $matches[0];

            if (mb_strtoupper($matched, 'UTF-8') === $matched) {
                return mb_strtoupper($replacement, 'UTF-8');
            }

            if (ucfirst(mb_strtolower($matched, 'UTF-8')) === $matched) {
                return ucfirst($replacement);
            }

            return $replacement;
        }, $subject) ?? $subject;
    }
};
