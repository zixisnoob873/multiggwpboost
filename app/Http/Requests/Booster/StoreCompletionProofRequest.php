<?php

namespace App\Http\Requests\Booster;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreCompletionProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'booster';
    }

    public function rules(): array
    {
        return [
            'completion_proof' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(4096)],
        ];
    }
}
