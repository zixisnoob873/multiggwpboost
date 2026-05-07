<?php

namespace App\Http\Requests\Admin;

use App\Models\BoosterApplication;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBoosterApplicationRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('people');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => trim((string) $this->input('status')),
            'admin_notes' => $this->normalizeNullableString('admin_notes', 2000),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(BoosterApplication::statusOptions()))],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var BoosterApplication|null $application */
                $application = $this->route('boosterApplication');
                $status = (string) $this->input('status');

                if (! $application instanceof BoosterApplication) {
                    return;
                }

                if (! $application->canTransitionTo($status)) {
                    $validator->errors()->add('status', 'This application cannot move to that status from its current state.');
                }

                if ($status === BoosterApplication::STATUS_HIRED && ! $application->isConverted()) {
                    $validator->errors()->add('status', 'Convert the application into a booster account before marking it as hired.');
                }
            },
        ];
    }
}
