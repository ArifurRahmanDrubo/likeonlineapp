<?php

namespace App\Http\Controllers;

use App\Models\SmsGateway;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;

class SmsSettingsController extends Controller
{
    /**
     * GET /api/admin/sms/gateways — fetch all gateways.
     */
    public function gateways()
    {
        return response()->json(SmsGateway::orderBy('id')->get());
    }

    /**
     * POST /api/admin/sms/gateways/{id}/activate — make this the single
     * active gateway (deactivates all others).
     */
    public function activate($id)
    {
        $gateway = SmsGateway::findOrFail($id);

        SmsGateway::where('is_active', true)->update(['is_active' => false]);
        $gateway->update(['is_active' => true]);

        return response()->json([
            'message' => "{$gateway->name} activated successfully",
            'gateways' => SmsGateway::orderBy('id')->get(),
        ]);
    }

    /**
     * PUT /api/admin/sms/gateways/{id} — update API key and Sender ID.
     */
    public function updateGateway(Request $request, $id)
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string|max:255',
            'sender_id' => 'nullable|string|max:255',
        ]);

        $gateway = SmsGateway::findOrFail($id);
        $gateway->update($validated);

        return response()->json([
            'message' => "{$gateway->name} updated successfully",
            'gateway' => $gateway->fresh(),
        ]);
    }

    /**
     * GET /api/admin/sms/templates — fetch all templates.
     */
    public function templates()
    {
        return response()->json(SmsTemplate::orderBy('id')->get());
    }

    /**
     * PUT /api/admin/sms/templates/{id} — update template body and enable toggle.
     */
    public function updateTemplate(Request $request, $id)
    {
        $validated = $request->validate([
            'template' => 'required|string',
            'is_enabled' => 'required|boolean',
        ]);

        $template = SmsTemplate::findOrFail($id);
        $template->update([
            'template' => $validated['template'],
            'is_enabled' => (bool) $validated['is_enabled'],
        ]);

        return response()->json([
            'message' => "{$template->title} updated successfully",
            'template' => $template->fresh(),
        ]);
    }
}
