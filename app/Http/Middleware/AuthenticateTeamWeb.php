<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTeamWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = $request->session()->get('team_id');

        if (! $teamId) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('team.login');
        }

        $team = Team::find($teamId);

        if (! $team || ($team->status ?? 'active') !== 'active') {
            $request->session()->forget('team_id');
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This team account has been disabled.'], 403);
            }
            return redirect()->route('team.login')->withErrors(['login' => 'Your team account has been disabled.']);
        }

        $request->setUserResolver(fn () => $team);

        return $next($request);
    }
}
