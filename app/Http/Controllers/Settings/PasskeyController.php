<?php

namespace App\Http\Controllers\Settings;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Livewirez\Webauthn\Passkey;
use Symfony\Component\Uid\Uuid;
use App\Http\Controllers\Controller;

class PasskeyController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('passkeys/Index', [
            'passkeys' => $request->user()->passkeys()->get()->map(static function (Passkey $source) {
                $data = $source->only(['id', 'name', 'public_key_credential_id', 'usage_count', 'device_name']);
                $data['counter'] = $source->data->counter;
                $data['aaguid'] = $source->data->aaguid;
                $data['user_handle'] = $source->data->userHandle;
                $data['backup_status'] = $source->data->backupStatus;
                $data['backup_eligible'] = $source->data->backupEligible;
                $data['aaguid'] = Uuid::fromString($source->data->aaguid)->toRfc4122();
                $data['public_key_credential_id_hex'] = bin2hex($data['public_key_credential_id']);
                $data['last_used_at'] = $source->last_used_at?->format('j M Y, g:i a');

                return (object) $data;
            }),
            'status' => $request->session()->get('status'),
        ]);
    }
}