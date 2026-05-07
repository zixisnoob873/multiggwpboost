<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentProviderDescriptor;

abstract class AbstractPaymentProvider implements PaymentProvider
{
    final public function descriptor(): PaymentProviderDescriptor
    {
        return new PaymentProviderDescriptor(...$this->descriptorData());
    }

    abstract protected function descriptorData(): array;
}
