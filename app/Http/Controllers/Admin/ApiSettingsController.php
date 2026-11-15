<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiSettingsController extends Controller
{
    /**
     * Display the dynamic API Settings & Integrations management hub.
     */
    public function index(): View
    {
        $pusher = ApiSetting::getGroup(ApiSetting::GROUP_PUSHER);
        $google = ApiSetting::getGroup(ApiSetting::GROUP_GOOGLE);
        $whatsapp = ApiSetting::getGroup(ApiSetting::GROUP_WHATSAPP);

        // Masked representations for secret display
        $maskedPusherSecret = ! empty($pusher['server_key']) ? '••••••••'.substr($pusher['server_key'], -4) : '';
        $maskedGoogleSecret = ! empty($google['client_secret']) ? '••••••••'.substr($google['client_secret'], -4) : '';
        $maskedGoogleApiKey = ! empty($google['api_key']) ? '••••••••'.substr($google['api_key'], -4) : '';
        $maskedWhatsappToken = ! empty($whatsapp['access_token']) ? '••••••••'.substr($whatsapp['access_token'], -4) : '';

        return view('admin.api-settings.index', [
            'pusher'             => $pusher,
            'google'             => $google,
            'whatsapp'           => $whatsapp,
            'maskedPusherSecret' => $maskedPusherSecret,
            'maskedGoogleSecret' => $maskedGoogleSecret,
            'maskedGoogleApiKey' => $maskedGoogleApiKey,
            'maskedWhatsappToken'=> $maskedWhatsappToken,
        ]);
    }

    /**
     * Update Pusher Beams credentials.
     */
    public function updatePusher(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'instance_id' => ['required', 'string', 'max:255'],
            'server_key'  => ['nullable', 'string', 'max:500'],
            'cluster'     => ['required', 'string', 'max:50'],
            'is_enabled'  => ['sometimes', 'boolean'],
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');

        ApiSetting::saveGroup(ApiSetting::GROUP_PUSHER, $validated);

        return redirect()->route('admin.api-settings.index')
            ->with('status', 'Pusher Beams configuration updated successfully.')
            ->with('active_tab', 'pusher');
    }

    /**
     * Update Google OAuth & Calendar API credentials.
     */
    public function updateGoogle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'     => ['required', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'redirect_uri'  => ['required', 'url', 'max:500'],
            'api_key'       => ['nullable', 'string', 'max:500'],
            'is_enabled'    => ['sometimes', 'boolean'],
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');

        ApiSetting::saveGroup(ApiSetting::GROUP_GOOGLE, $validated);

        return redirect()->route('admin.api-settings.index')
            ->with('status', 'Google OAuth & Calendar API configuration updated successfully.')
            ->with('active_tab', 'google');
    }

    /**
     * Update WhatsApp API & Click-to-Chat configurations.
     */
    public function updateWhatsapp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider'             => ['required', 'string', 'in:click_to_chat,cloud_api'],
            'base_url'             => ['required', 'url', 'max:255'],
            'message_template'     => ['required', 'string', 'max:1000'],
            'phone_number_id'      => ['nullable', 'string', 'max:100'],
            'access_token'         => ['nullable', 'string', 'max:500'],
            'is_enabled'           => ['sometimes', 'boolean'],
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');

        ApiSetting::saveGroup(ApiSetting::GROUP_WHATSAPP, $validated);

        return redirect()->route('admin.api-settings.index')
            ->with('status', 'WhatsApp integration settings updated successfully.')
            ->with('active_tab', 'whatsapp');
    }

}
