<?php

namespace App\Services\Privacy;

use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserHistoryRedactionService
{
    public function redactCustomerHistory(User $user, string $placeholder = '[redacted customer]'): void
    {
        $orders = $user->orders()->get(['id', 'details', 'metadata', 'whatsapp', 'discord']);
        $tokens = $this->redactionTokens($user, $orders);

        $this->redactOrders($orders, $tokens, $placeholder);
        $this->redactChatMessages($user, $orders->pluck('id'), $tokens, $placeholder);
    }

    public function redactBoosterHistory(User $user, string $placeholder = '[redacted booster]'): void
    {
        $orders = $user->boosterOrders()->get(['id', 'details', 'metadata', 'whatsapp', 'discord']);
        $tokens = $this->redactionTokens($user, $orders);

        $this->redactOrders($orders, $tokens, $placeholder);
        $this->redactChatMessages($user, $orders->pluck('id'), $tokens, $placeholder);
    }

    protected function redactOrders(Collection $orders, array $tokens, string $placeholder): void
    {
        $orders->each(function (Order $order) use ($tokens, $placeholder): void {
            $details = is_array($order->details) ? $order->details : [];
            $detailsChanged = false;

            foreach (['notes', 'adminNotes'] as $field) {
                if (array_key_exists($field, $details)) {
                    $redacted = $this->redactText($details[$field], $tokens, $placeholder);
                    $detailsChanged = $detailsChanged || $redacted !== $details[$field];
                    $details[$field] = $redacted;
                }
            }

            if (isset($details['order']) && is_array($details['order'])) {
                foreach (['notes', 'adminNotes'] as $field) {
                    if (array_key_exists($field, $details['order'])) {
                        $redacted = $this->redactText($details['order'][$field], $tokens, $placeholder);
                        $detailsChanged = $detailsChanged || $redacted !== $details['order'][$field];
                        $details['order'][$field] = $redacted;
                    }
                }
            }

            if ($detailsChanged) {
                $order->forceFill([
                    'details' => $details,
                ])->save();
            }
        });
    }

    protected function redactionTokens(User $user, Collection $orders): array
    {
        $tokens = collect([
            $user->email,
            $user->name,
            $user->first_name,
            $user->last_name,
            trim(implode(' ', array_filter([$user->first_name, $user->last_name]))),
        ]);

        foreach ($orders as $order) {
            $metadata = is_array($order->metadata) ? $order->metadata : [];
            $tokens->push($order->whatsapp);
            $tokens->push($order->discord);
            $tokens->push(data_get($metadata, 'customer.email'));
            $tokens->push(data_get($metadata, 'customer.name'));
            $tokens->push(data_get($metadata, 'customer.whatsapp'));
            $tokens->push(data_get($metadata, 'customer.discord'));
        }

        return $tokens
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->sortByDesc(fn ($value) => strlen($value))
            ->values()
            ->all();
    }

    protected function redactChatMessages(User $user, Collection $orderIds, array $tokens, string $placeholder): void
    {
        $ids = $orderIds
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        OrderChatMessage::query()
            ->whereHas('thread', fn ($query) => $query->whereIn('order_id', $ids))
            ->select(['id', 'order_chat_thread_id', 'sender_id', 'sender_name', 'body'])
            ->chunkById(200, function (Collection $messages) use ($placeholder, $tokens, $user): void {
                $messages->each(function (OrderChatMessage $message) use ($placeholder, $tokens, $user): void {
                    $updates = [];
                    $redactedBody = $this->redactText($message->body, $tokens, $placeholder);

                    if ($redactedBody !== $message->body) {
                        $updates['body'] = $redactedBody;
                    }

                    if ((int) $message->sender_id === (int) $user->getKey() && $message->sender_name !== $placeholder) {
                        $updates['sender_name'] = $placeholder;
                    }

                    if ($updates !== []) {
                        $message->forceFill($updates)->save();
                    }
                });
            });
    }

    protected function redactText(mixed $value, array $tokens, string $placeholder): mixed
    {
        if (! is_string($value) || $tokens === []) {
            return $value;
        }

        $redacted = $value;

        foreach ($tokens as $token) {
            if (! is_string($token) || trim($token) === '') {
                continue;
            }

            $redacted = preg_replace('/'.preg_quote($token, '/').'/iu', $placeholder, $redacted) ?? $redacted;
        }

        return Str::of($redacted)->squish()->toString();
    }
}
