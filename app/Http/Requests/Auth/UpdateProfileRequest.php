<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('update', $user) ?? false;
    }

    public function rules(): array
    {
        return [
            'profile_photo' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(4096)],
        ];
    }
}
