<?php

use App\Enums\OrderChatThreadType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_chat_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('thread_type', OrderChatThreadType::values());
            $table->timestamps();

            $table->unique(['order_id', 'thread_type']);
            $table->index('thread_type');
        });

        $this->backfillExistingOrders();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_chat_threads');
    }

    protected function backfillExistingOrders(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $threadTypes = OrderChatThreadType::values();
        $timestamp = now();

        DB::table('orders')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($threadTypes, $timestamp): void {
                $rows = [];

                foreach ($orders as $order) {
                    foreach ($threadTypes as $threadType) {
                        $rows[] = [
                            'order_id' => $order->id,
                            'thread_type' => $threadType,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('order_chat_threads')->upsert(
                        $rows,
                        ['order_id', 'thread_type'],
                        ['updated_at']
                    );
                }
            });
    }
};
