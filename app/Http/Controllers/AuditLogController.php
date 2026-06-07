<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->user_id,  fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->event,    fn($q) => $q->where('event', $request->event))
            ->when($request->module,   fn($q) => $q->where('module', $request->module))
            ->when($request->from,     fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,       fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->search,   fn($q) => $q->where('description', 'like', "%{$request->search}%")
                                                    ->orWhere('ip_address', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate(50);

        $users   = User::orderBy('name')->get(['id', 'name']);
        $events  = AuditLog::distinct()->pluck('event')->sort()->values();
        $modules = AuditLog::distinct()->whereNotNull('module')->pluck('module')->sort()->values();

        return view('audit.index', compact('logs', 'users', 'events', 'modules'));
    }
}
