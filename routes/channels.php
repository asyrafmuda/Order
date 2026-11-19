<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| "orders.{uuid}" and "shop" are public channels: they only carry an order
| reference and status, and the UUID is not guessable. Staff screens listen
| on the private "staff" channel.
*/

Broadcast::channel('staff', fn (User $user) => $user->isStaff());
