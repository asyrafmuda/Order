<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    /**
     * Riders may only act on deliveries assigned to them.
     */
    public function update(User $user, Delivery $delivery): bool
    {
        return $user->isAdmin() || ($user->rider && $delivery->rider_id === $user->rider->id);
    }
}
