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
//
// The `guards` option tells the framework's retrieveUser() which auth guards
// to try, in order, so the SAME ownership check works for:
//   - the web /broadcasting/auth path (session `employee`/`web` guards), and
//   - the Flutter app's Bearer-token path (`sanctum` guard).
// An employee may only ever listen to their OWN channel.
Broadcast::channel('user.{id}.notifications', function ($user, $id) {
    return $user instanceof \App\Models\User && (int) $user->id === (int) $id;
}, ['guards' => ['employee', 'web', 'sanctum']]);
