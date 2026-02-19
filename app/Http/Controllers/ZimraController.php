<?php

namespace App\Http\Controllers;

use App\Models\ZimraConfig;
use App\Services\ZimraDeviceService;
use Illuminate\Http\Request;

class ZimraController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Store ZIMRA Configuration
    |--------------------------------------------------------------------------
    */
    public function storeConfig(Request $request)
    {
        $validated = $request->validate([
            'base_url' => 'required|url',
            'device_model' => 'required|string',
            'device_version' => 'required|string',
        ]);

        // Deactivate existing configs
        ZimraConfig::where('is_active', true)->update(['is_active' => false]);

        $config = ZimraConfig::create([
            'base_url' => $validated['base_url'],
            'device_model' => $validated['device_model'],
            'device_version' => $validated['device_version'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'ZIMRA configuration stored successfully',
            'config' => $config,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Active ZIMRA Configuration
    |--------------------------------------------------------------------------
    */
    public function getActiveConfig()
    {
        $config = ZimraConfig::getActive();

        if (!$config) {
            return response()->json([
                'message' => 'No active ZIMRA configuration found',
            ], 404);
        }

        return response()->json($config);
    }

    /*
    |--------------------------------------------------------------------------
    | Update ZIMRA Configuration
    |--------------------------------------------------------------------------
    */
    public function updateConfig(Request $request, int $id)
    {
        $config = ZimraConfig::findOrFail($id);

        $validated = $request->validate([
            'base_url' => 'sometimes|url',
            'device_model' => 'sometimes|string',
            'device_version' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ]);

        // If setting this config as active, deactivate others
        if (isset($validated['is_active']) && $validated['is_active']) {
            ZimraConfig::where('id', '!=', $id)->update(['is_active' => false]);
        }

        $config->update($validated);

        return response()->json([
            'message' => 'ZIMRA configuration updated successfully',
            'config' => $config,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Register Device
    |--------------------------------------------------------------------------
    */
    public function register(Request $request, ZimraDeviceService $zimra)
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'serial_number' => 'required|string',
            'activation_key' => 'required|string',
        ]);

        try {
            $result = $zimra->registerDevice(
                $validated['device_id'],
                $validated['serial_number'],
                $validated['activation_key']
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Device Config (mTLS)
    |--------------------------------------------------------------------------
    */
    public function config(ZimraDeviceService $zimra)
    {
        try {
            return response()->json($zimra->getConfig());
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
