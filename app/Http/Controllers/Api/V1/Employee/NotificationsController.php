<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Employee notifications — the database-notification rows pushed to the
 * employee (order, payment, subscription events). Read-only list plus
 * mark-read / mark-all-read; the web panel lists the same rows.
 */
class NotificationsController extends Controller
{
    use EmployeeScoped;

    /**
     * GET /api/v1/employee/notifications
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $notifications = $employee->notifications()
            ->orderByDesc('created_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return response()->json([
            'meta' => [
                'unread_count' => $employee->unreadNotifications()->count(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'data' => $this->serialize(collect($notifications->items())),
        ]);
    }

    /**
     * POST /api/v1/employee/notifications/{id}/read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $notification = $employee->notifications()->whereKey($id)->first();

        if (! $notification) {
            throw ValidationException::withMessages([
                'notification' => 'Notification not found or not accessible.',
            ]);
        }

        $notification->markAsRead();

        return response()->json([
            'data' => $this->serialize([$notification->fresh()]),
        ]);
    }

    /**
     * POST /api/v1/employee/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        // The property (not the relation) is the loaded collection; a
        // markAsRead on a fresh collection is a bulk UPDATE by the
        // underlying relation's query, exactly as the web panel does it.
        $count = $employee->unreadNotifications()->count();
        $employee->unreadNotifications->markAsRead();

        return response()->json(['message' => "All notifications marked read ({$count} unread)."]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>|list<\Illuminate\Database\Eloquent\Model> $items
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serialize(\Illuminate\Support\Collection|array $items): array
    {
        return collect($items)->map(fn ($n) => [
            'id' => $n->id,
            'type' => class_basename($n),
            'data' => $n->data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ])->all();
    }
}
