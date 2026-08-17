<?php

namespace App\Http\Controllers;

use App\Models\SmsLog;
use Illuminate\Http\Request;

class SmsLogController extends Controller
{
    /**
     * GET /api/admin/sms/logs — paginated SMS logs with search and filters.
     *
     * Query params:
     *   search   — match against recipient or message
     *   status   — success | failed
     *   provider — gateway display name
     *   date_from / date_to — created_at range (YYYY-MM-DD)
     */
    public function index(Request $request)
    {
        $query = SmsLog::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($provider = $request->input('provider')) {
            $query->where('provider', $provider);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->latest('created_at')->paginate($request->input('per_page', 15));

        // The column set is declared by the backend so the frontend renders
        // exactly the fields that are available/used in the log rows.
        $columns = [
            ['field' => 'created_at', 'header' => 'Date & Time', 'type' => 'datetime'],
            ['field' => 'recipient', 'header' => 'Recipient', 'type' => 'text'],
            ['field' => 'template_key', 'header' => 'Template / Type', 'type' => 'template_key'],
            ['field' => 'provider', 'header' => 'Provider', 'type' => 'text'],
            ['field' => 'message', 'header' => 'Message Preview', 'type' => 'message'],
            ['field' => 'status', 'header' => 'Status', 'type' => 'status'],
            ['field' => 'response', 'header' => 'Response', 'type' => 'action'],
        ];

        return response()->json([
            'data' => $logs->items(),
            'total' => $logs->total(),
            'per_page' => $logs->perPage(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'columns' => $columns,
        ]);
    }
}
