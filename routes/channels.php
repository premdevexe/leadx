<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// The channel id is a User primary key. Restrict authorization to the web
// (User) resolver explicitly so a differently-typed actor (e.g. a Team
// model if the team web resolver is ever re-enabled) can't claim a
// notification channel whose numeric id happens to collide with one of
// their own.
Broadcast::channel('user.{id}.notifications', function ($user, $id) {
    return $user instanceof \App\Models\User && (int) $user->id === (int) $id;
});
