<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdminViewer = (bool) $request->user()?->isAdminUser();
        $displayName = $isAdminViewer ? $this->fullIdentity() : $this->publicIdentity();

        return array_filter([
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'display_name' => $displayName,
            'email' => $isAdminViewer ? $this->email : null,
            'role' => $isAdminViewer ? $this->role : null,
            'account_status' => $isAdminViewer ? $this->account_status : null,
        ], fn ($value) => $value !== null);
    }
}
