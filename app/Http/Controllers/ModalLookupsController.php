<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Upzila;
use App\Models\Zone;
use App\Models\ClientType;
use App\Models\ConnectionType;
use App\Models\Package;
use App\Models\CustomerBillingStatus;
use Illuminate\Http\Request;

class ModalLookupsController extends Controller
{
    /**
     * Consolidated endpoint for modal lookup data.
     * Returns districts, upazilas, zones, client types, connection types,
     * packages, and billing statuses in a single response payload.
     * Only fetches key UI columns with lightweight select().
     */
    public function getModalLookups()
    {
        try {
            return response()->json([
                'districts'        => District::select('id', 'districtname')->get(),
                'upazilas'         => Upzila::select('id', 'upzilaname')->get(),
                'zones'            => Zone::select('id', 'zone_name')->get(),
                'client_types'     => ClientType::select('id', 'client_type')->get(),
                'connection_types' => ConnectionType::select('id', 'connection_type')->get(),
                'packages'         => Package::select('id', 'packagename', 'price')->get(),
                'billing_statuses' => CustomerBillingStatus::select('id', 'billingstatus')->get(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch modal lookups',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
