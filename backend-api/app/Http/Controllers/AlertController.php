<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Get alerts log (ordered by latest)
     */
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 50)));
        $unreadOnly = filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOLEAN);

        $query = Alert::with('device')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        $alerts = $query->limit($limit)->get()->map(function (Alert $alert) {
            return [
                'id' => $alert->id,
                'type' => $alert->type,
                'title' => $alert->title,
                'message' => $alert->message,
                'is_read' => $alert->is_read,
                'device' => $alert->device ? [
                    'id' => $alert->device->id,
                    'display_name' => $alert->device->device_name ?: ($alert->device->metadata['hostname'] ?? $alert->device->ip_address),
                    'ip_address' => $alert->device->ip_address,
                    'mac_address' => $alert->device->mac_address,
                ] : null,
                'created_at' => $alert->created_at?->toIso8601String(),
            ];
        });

        $unreadCount = Alert::where('is_read', false)->count();

        return response()->json([
            'success' => true,
            'data' => $alerts,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark an alert as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        $alert = Alert::findOrFail($id);
        $alert->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Alert marked as read',
        ]);
    }

    /**
     * Mark all alerts as read
     */
    public function markAllAsRead(): JsonResponse
    {
        Alert::where('is_read', false)->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All alerts marked as read',
        ]);
    }

    /**
     * Delete all alerts (clear log)
     */
    public function destroy(): JsonResponse
    {
        Alert::truncate();

        return response()->json([
            'success' => true,
            'message' => 'Alerts log cleared successfully',
        ]);
    }
}
