<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    /**
     * GET /notifications
     * List the authenticated user's notifications, optionally filtered by category.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when(
                $request->filled('category'),
                fn($q) => $q->where('data->category', $request->category)
            )
            ->when(
                $request->boolean('unread'),
                fn($q) => $q->whereNull('read_at')
            )
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse([
            'unread_count'  => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ], 'Notifications retrieved successfully.');
    }

    /**
     * PATCH /notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if (! $notification) {
            return $this->jsonResponse(null, 'Notification not found.', 404);
        }

        $notification->markAsRead();

        return $this->jsonResponse($notification->fresh(), 'Notification marked as read.');
    }

    /**
     * PATCH /notifications/read-all
     * Mark all of the user's unread notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->jsonResponse(null, 'All notifications marked as read.');
    }
}
