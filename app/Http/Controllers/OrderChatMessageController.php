<?php

namespace App\Http\Controllers;

use App\Enums\OrderChatThreadType;
use App\Http\Requests\Chat\IndexOrderChatMessagesRequest;
use App\Http\Requests\Chat\StoreOrderChatMessageRequest;
use App\Http\Resources\OrderChatMessageResource;
use App\Models\Order;
use App\Services\Chat\EnsureOrderChatThreads;
use App\Services\Chat\OrderChatAuthorizationService;
use App\Services\Chat\OrderChatHistoryService;
use App\Services\Chat\SendOrderChatMessage;
use Illuminate\Http\JsonResponse;

class OrderChatMessageController extends Controller
{
    public function __construct(
        protected OrderChatAuthorizationService $authorizationService,
        protected EnsureOrderChatThreads $ensureOrderChatThreads,
        protected OrderChatHistoryService $historyService,
        protected SendOrderChatMessage $sendOrderChatMessage
    ) {}

    public function index(IndexOrderChatMessagesRequest $request, Order $order, string $threadType): JsonResponse
    {
        $resolvedThreadType = $this->resolveThreadType($threadType);
        $user = $request->user();

        $this->authorizationService->authorizeViewThread($user, $order, $resolvedThreadType);

        $thread = $this->ensureOrderChatThreads->thread($order, $resolvedThreadType);
        $history = $this->historyService->load(
            $thread,
            (int) ($request->validated()['limit'] ?? 25),
            isset($request->validated()['before']) ? (int) $request->validated()['before'] : null
        );

        return response()->json([
            'thread' => [
                'order_id' => $order->getKey(),
                'thread_type' => $resolvedThreadType->value,
            ],
            'messages' => OrderChatMessageResource::collection($history['messages'])->resolve($request),
            'meta' => [
                'has_more' => $history['has_more'],
                'next_cursor' => $history['next_cursor'],
            ],
        ]);
    }

    public function store(StoreOrderChatMessageRequest $request, Order $order, string $threadType): JsonResponse
    {
        $resolvedThreadType = $this->resolveThreadType($threadType);
        $user = $request->user();

        $this->authorizationService->authorizeSendToThread($user, $order, $resolvedThreadType);

        $message = $this->sendOrderChatMessage->execute(
            $order,
            $resolvedThreadType,
            $user,
            $request->validated()['body']
        );

        return response()->json([
            'message' => OrderChatMessageResource::make($message)->resolve($request),
        ], 201);
    }

    protected function resolveThreadType(string $threadType): OrderChatThreadType
    {
        $resolved = OrderChatThreadType::tryFrom($threadType);

        abort_if($resolved === null, 404);

        return $resolved;
    }
}
