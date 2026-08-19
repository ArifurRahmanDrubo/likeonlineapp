<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;

/**
 * Admin management of the DB-driven payment gateways (bKash / Nagad /
 * SSLCommerz). Credentials are stored in the `payment_gateways` table and
 * applied dynamically by PaymentGatewayService at payment time.
 */
class PaymentGatewayController extends Controller
{
    /**
     * GET /api/admin/payment-gateways
     */
    public function index()
    {
        $gateways = PaymentGateway::orderBy('id')->get();

        return response()->json([
            'status' => 'success',
            'gateways' => $gateways,
        ]);
    }

    /**
     * PUT /api/admin/payment-gateways/{id}
     *
     * Instant save per gateway card: title, mode, is_active and the
     * gateway-specific credential keys (validated against the whitelist).
     */
    public function update(Request $request, int $id)
    {
        $gateway = PaymentGateway::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|nullable|string|max:255',
            'mode' => 'sometimes|nullable|in:sandbox,live',
            'is_active' => 'sometimes|nullable|boolean',
            'credentials' => 'sometimes|nullable|array',
        ]);

        if (array_key_exists('title', $validated)) {
            $gateway->title = $validated['title'] ?? $gateway->title;
        }
        if (array_key_exists('mode', $validated)) {
            $gateway->mode = $validated['mode'] ?? $gateway->mode;
        }
        if (array_key_exists('is_active', $validated)) {
            $gateway->is_active = (bool) $validated['is_active'];
        }

        // Merge credentials against the gateway's whitelist so a partial save
        // never wipes keys the UI does not render for this gateway.
        if (isset($validated['credentials']) && is_array($validated['credentials'])) {
            $whitelist = PaymentGateway::CREDENTIAL_FIELDS[$gateway->name] ?? [];
            $merged = $gateway->credentials ?? [];
            foreach ($whitelist as $key) {
                if (array_key_exists($key, $validated['credentials'])) {
                    $merged[$key] = (string) $validated['credentials'][$key];
                }
            }
            $gateway->credentials = $merged;
        }

        $gateway->save();

        return response()->json([
            'status' => 'success',
            'message' => "{$gateway->title} settings saved.",
            'gateway' => $gateway,
        ]);
    }

    /**
     * POST /api/admin/payment-gateways/{id}/activate
     *
     * Toggle a gateway's is_active flag. Unlike SMS gateways, multiple
     * payment gateways may be active at once — the checkout page shows each
     * active gateway as a selectable option.
     */
    public function toggleActive(int $id)
    {
        $gateway = PaymentGateway::findOrFail($id);
        $gateway->is_active = !$gateway->is_active;
        $gateway->save();

        return response()->json([
            'status' => 'success',
            'message' => $gateway->is_active
                ? "{$gateway->title} is now active."
                : "{$gateway->title} is now inactive.",
            'gateway' => $gateway,
        ]);
    }
}
