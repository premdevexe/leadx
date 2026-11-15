<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

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
        $gateway = ApiSetting::getGroup(ApiSetting::GROUP_GATEWAY);
        $webhook = ApiSetting::getGroup(ApiSetting::GROUP_WEBHOOK);

        // Masked representations for secret display
        $maskedPusherSecret = ! empty($pusher['server_key']) ? '••••••••'.substr($pusher['server_key'], -4) : '';
        $maskedGoogleSecret = ! empty($google['client_secret']) ? '••••••••'.substr($google['client_secret'], -4) : '';
        $maskedWhatsappToken = ! empty($whatsapp['access_token']) ? '••••••••'.substr($whatsapp['access_token'], -4) : '';
        $maskedGatewaySecret = ! empty($gateway['secret_key']) ? '••••••••'.substr($gateway['secret_key'], -4) : '';
        $maskedWebhookSecret = ! empty($gateway['webhook_secret']) ? '••••••••'.substr($gateway['webhook_secret'], -4) : '';
        $maskedOutboundWebhookSecret = ! empty($webhook['secret_token']) ? '••••••••'.substr($webhook['secret_token'], -4) : '';

        $tokens = PersonalAccessToken::orderBy('created_at', 'desc')->get();

        return view('admin.api-settings.index', [
            'pusher'                      => $pusher,
            'google'                      => $google,
            'whatsapp'                    => $whatsapp,
            'gateway'                     => $gateway,
            'webhook'                     => $webhook,
            'maskedPusherSecret'          => $maskedPusherSecret,
            'maskedGoogleSecret'          => $maskedGoogleSecret,
            'maskedWhatsappToken'         => $maskedWhatsappToken,
            'maskedGatewaySecret'         => $maskedGatewaySecret,
            'maskedWebhookSecret'         => $maskedWebhookSecret,
            'maskedOutboundWebhookSecret' => $maskedOutboundWebhookSecret,
            'tokens'                      => $tokens,
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
            'default_country_code' => ['required', 'string', 'max:10'],
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

    /**
     * Update Payment Gateway API & Webhook configurations.
     */
    public function updatePaymentGateway(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver'         => ['required', 'string', 'in:mock,razorpay,stripe,phonepe,cashfree,custom'],
            'environment'    => ['required', 'string', 'in:sandbox,live'],
            'key_id'         => ['nullable', 'string', 'max:255'],
            'secret_key'     => ['nullable', 'string', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:500'],
            'is_enabled'     => ['sometimes', 'boolean'],
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');

        ApiSetting::saveGroup(ApiSetting::GROUP_GATEWAY, $validated);

        return redirect()->route('admin.api-settings.index')
            ->with('status', 'Payment Gateway API credentials updated successfully.')
            ->with('active_tab', 'gateway');
    }

    /**
     * Update Outbound Webhooks configuration.
     */
    public function updateWebhook(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint_url' => ['nullable', 'url', 'max:500'],
            'secret_token' => ['nullable', 'string', 'max:255'],
            'events'       => ['array'],
            'events.*'     => ['string'],
            'is_enabled'   => ['sometimes', 'boolean'],
        ]);

        $validated['events'] = $request->input('events', []);
        $validated['is_enabled'] = $request->boolean('is_enabled');

        ApiSetting::saveGroup(ApiSetting::GROUP_WEBHOOK, $validated);

        return redirect()->route('admin.api-settings.index')
            ->with('status', 'Outbound Webhooks configuration saved successfully.')
            ->with('active_tab', 'webhooks');
    }

    /**
     * Test Outbound Webhook endpoint with ping payload.
     */
    public function testWebhook(Request $request): RedirectResponse
    {
        $webhook = ApiSetting::getGroup(ApiSetting::GROUP_WEBHOOK);
        $url = $webhook['endpoint_url'] ?? '';

        if (empty($url)) {
            return redirect()->route('admin.api-settings.index')
                ->with('error', 'Please configure an Endpoint URL before testing.')
                ->with('active_tab', 'webhooks');
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-LeadX-Webhook-Event' => 'ping',
                    'X-LeadX-Signature'     => hash_hmac('sha256', 'ping', $webhook['secret_token'] ?? ''),
                ])
                ->post($url, [
                    'event'     => 'ping',
                    'message'   => 'LeadX Webhook Test Ping',
                    'timestamp' => now()->toIso8601String(),
                ]);

            if ($response->successful()) {
                return redirect()->route('admin.api-settings.index')
                    ->with('status', 'Webhook ping successful! Server returned HTTP '.$response->status().'.')
                    ->with('active_tab', 'webhooks');
            }

            return redirect()->route('admin.api-settings.index')
                ->with('error', 'Webhook ping returned HTTP '.$response->status().': '.$response->body())
                ->with('active_tab', 'webhooks');
        } catch (\Throwable $e) {
            return redirect()->route('admin.api-settings.index')
                ->with('error', 'Webhook ping failed: '.$e->getMessage())
                ->with('active_tab', 'webhooks');
        }
    }

    /**
     * Create a new personal API access token for LeadX external integrations.
     */
    public function createToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token_name' => ['required', 'string', 'max:100'],
            'abilities'  => ['nullable', 'string'],
        ]);

        $admin = auth('admin')->user();
        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $abilities = ['*'];
        if (! empty($validated['abilities'])) {
            $abilities = array_values(array_filter(array_map('trim', explode(',', $validated['abilities']))));
        }

        $token = $admin->createToken($validated['token_name'], $abilities);

        return redirect()->route('admin.api-settings.index')
            ->with('status', 'New API Token created successfully.')
            ->with('new_token', $token->plainTextToken)
            ->with('new_token_name', $validated['token_name'])
            ->with('active_tab', 'tokens');
    }

    /**
     * Revoke an active personal access token.
     */
    public function revokeToken(PersonalAccessToken $token): RedirectResponse
    {
        $token->delete();

        return redirect()->route('admin.api-settings.index')
            ->with('status', 'API Token "'.$token->name.'" revoked successfully.')
            ->with('active_tab', 'tokens');
    }
}
