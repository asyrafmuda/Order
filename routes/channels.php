<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| "orders.{uuid}" and "shop.{outlet}" are public channels: they only carry an
| order reference and status, and the UUID is not guessable. Staff screens
| listen on the private "staff.{outlet}" channel of the outlet they work in.
*/

Broadcast::channel('staff.{outletId}', fn (User $user, int $outletId) => $user->isStaff() && $user->worksIn($outletId));
