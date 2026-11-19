<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;

class LogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')->latest()->paginate(15);

        return view('dinas.log', compact('logs'));
    }
}