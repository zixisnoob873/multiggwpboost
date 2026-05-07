<?php

namespace App\Http\Resources;

use App\Support\OrderChatMessagePayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return OrderChatMessagePayload::make($this->resource, $request->user());
    }
}
