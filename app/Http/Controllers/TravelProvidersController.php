<?php

namespace App\Http\Controllers;

class TravelProvidersController extends Controller
{
    public function status()
    {
        $tp = config('travel.providers.travelpayouts', []);

        return response()->json([
            'default_provider' => config('travel.default_provider', 'manual'),
            'services' => ['flight','hotel','car'],
            'providers' => [
                'manual' => [
                    'enabled' => true,
                    'mode' => 'manual_request_quote',
                ],
                'travelpayouts' => [
                    'enabled' => (bool)($tp['enabled'] ?? false),
                    'configured' => !empty($tp['api_token']) && !empty($tp['marker']),
                    'mode' => 'ready_for_api_activation',
                ],
            ],
        ]);
    }
}
