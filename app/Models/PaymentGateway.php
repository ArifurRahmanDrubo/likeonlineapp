<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'credentials' => 'array',
        'is_active'   => 'boolean',
    ];

    /**
     * The gateway's credential keys, keyed by gateway name. The admin UI
     * renders exactly these fields per gateway card.
     */
    public const CREDENTIAL_FIELDS = [
        'bkash'       => ['app_key', 'app_secret', 'username', 'password'],
        'nagad'       => ['merchant_id', 'public_key', 'private_key'],
        'sslcommerz'  => ['store_id', 'store_password'],
    ];

    /**
     * Fetch a credential value, falling back to the legacy .env-driven config
     * so gateways keep working before any DB row is edited.
     */
    public function credential(string $key, $fallback = null)
    {
        $credentials = $this->credentials ?? [];

        return $credentials[$key] ?? $fallback;
    }
}
