<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Prehliadač auditného logu – kto, kedy a čo v administrácii zmenil. */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%"));
        }

        $logs = $query->paginate(50)->withQueryString();

        $logs->getCollection()->transform(fn (ActivityLog $log) => [
            'id'           => $log->id,
            'user_name'    => $log->user_name,
            'user_email'   => $log->user_email,
            'action'       => $log->action,
            'action_label' => $log->action_label,
            'description'  => $log->description,
            'properties'   => $log->properties,
            'ip_address'   => $log->ip_address,
            'created_at'   => $log->created_at?->format('d.m.Y H:i:s'),
        ]);

        return Inertia::render('Admin/ActivityLog/Index', [
            'logs'    => $logs,
            'filters' => $request->only('user_id', 'action', 'search'),
            'actions' => ActivityLog::ACTIONS,
            'users'   => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
