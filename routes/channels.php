<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| قنوات البث الخاصة. كل قناة تتحقق من الصلاحية عبر Policy الموديول المالك.
*/

Broadcast::channel('user.{userId}', fn ($user, int $userId) => (int) $user->id === $userId);
