<?php

namespace App\Http\Controllers;

use App\Models\ZimraConfig;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $companies = ZimraConfig::select('id', 'company_name', 'device_id', 'is_active', 'fiscal_day_status')
            ->orderBy('company_name')
            ->get();

        return view('dashboard', compact('user', 'companies'));
    }
}
