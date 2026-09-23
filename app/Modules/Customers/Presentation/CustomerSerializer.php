<?php

declare(strict_types=1);

namespace App\Modules\Customers\Presentation;

use App\Modules\Customers\Domain\Customer;

class CustomerSerializer
{
    public function toArray(Customer $customer): array
    {
        return [
            'customer_id' => $customer->getCustomerId(),
            'first_name' => $customer->getFirstName(),
            'last_name_paternal' => $customer->getLastNamePaternal(),
            'last_name_maternal' => $customer->getLastNameMaternal() ?? '',
            'full_name' => $customer->getFullName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone() ?? '',
            'is_active' => $customer->isActive(),
            'created_at' => $customer->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $customer->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'last_login' => $customer->getLastLogin()?->format('Y-m-d H:i:s'),
        ];
    }
}
