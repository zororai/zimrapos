<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveCompanyDevice Middleware
 * 
 * Enforces strict company-device isolation for ZIMRA FDMS operations.
 * 
 * CRITICAL SECURITY:
 * - Prevents cross-company device access
 * - Attaches correct device_id to request context
 * - Blocks unauthorized device usage
 * - Ensures fiscal data isolation
 */
class ResolveCompanyDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // STEP 1: Authenticate user
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'User must be authenticated to access fiscal operations',
            ], 401);
        }
        
        // STEP 2: Load user's company
        $companyId = $user->company_id ?? null;
        
        if (!$companyId) {
            Log::warning('USER_WITHOUT_COMPANY', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'error' => 'NoCompanyAssigned',
                'message' => 'User is not assigned to any company',
            ], 403);
        }
        
        $company = Company::find($companyId);
        
        if (!$company) {
            Log::error('COMPANY_NOT_FOUND', [
                'user_id' => $user->id,
                'company_id' => $companyId,
            ]);
            
            return response()->json([
                'error' => 'CompanyNotFound',
                'message' => 'Company not found',
            ], 404);
        }
        
        // STEP 3: Verify company is active
        if (!$company->is_active) {
            Log::warning('INACTIVE_COMPANY_ACCESS_ATTEMPT', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'company_name' => $company->name,
            ]);
            
            return response()->json([
                'error' => 'CompanyInactive',
                'message' => 'Company is not active',
            ], 403);
        }
        
        // STEP 4: Load active device from company_devices
        $activeDevice = \App\Models\CompanyDevice::getActiveForCompany($company->id);
        
        if (!$activeDevice) {
            Log::error('NO_ACTIVE_DEVICE', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'company_name' => $company->name,
            ]);
            
            return response()->json([
                'error' => 'NoActiveDevice',
                'message' => 'Company has no active device configured',
            ], 403);
        }
        
        $deviceId = $activeDevice->device_id;
        
        // STEP 5: CRITICAL - Check if request is trying to access a different device
        $requestedDeviceId = $request->input('device_id') 
            ?? $request->route('device_id') 
            ?? $request->route('deviceId');
        
        if ($requestedDeviceId && (int)$requestedDeviceId !== $deviceId) {
            Log::critical('UNAUTHORIZED_DEVICE_ACCESS_ATTEMPT', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'company_device_id' => $deviceId,
                'requested_device_id' => $requestedDeviceId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'timestamp' => now()->toIso8601String(),
            ]);
            
            return response()->json([
                'error' => 'UnauthorizedDeviceAccess',
                'message' => "You are not authorized to access device {$requestedDeviceId}. Your company's active device is {$deviceId}.",
            ], 403);
        }
        
        // STEP 6: Inject device_id and company into request
        // CRITICAL: This is the ONLY way device_id should be set
        $request->attributes->set('company', $company);
        $request->attributes->set('device_id', $deviceId);
        $request->attributes->set('company_device', $activeDevice);
        
        Log::info('DEVICE_ACCESS_AUTHORIZED', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'device_id' => $deviceId,
            'url' => $request->path(),
        ]);
        
        return $next($request);
    }
}
