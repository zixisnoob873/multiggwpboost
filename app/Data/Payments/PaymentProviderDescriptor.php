<?php

namespace App\Data\Payments;

class PaymentProviderDescriptor
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $notice,
        public readonly string $submitLabel,
        public readonly bool $isAvailable = true,
        public readonly bool $isDefault = false,
        public readonly bool $isConfigured = true,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'notice' => $this->notice,
            'submitLabel' => $this->submitLabel,
            'isAvailable' => $this->isAvailable,
            'isDefault' => $this->isDefault,
            'isConfigured' => $this->isConfigured,
        ];
    }
}
