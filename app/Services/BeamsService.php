<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;

class BeamsService
{
    /**
     * Publish a Web push notification to the given Beams interests.
     *
     * The Beams server key is kept in config (env: PUSHER_SERVER_KEY) so it
     * never reaches the browser.
     *
     * @param  string[]  $interests
     * @return array{publish_id: ?string, status: int}
     */
    public function publishWebNotification(string $title, string $body, array $interests): array
    {
        $instanceId = config('services.pusher.instance_id');
        $key = config('services.pusher.server_key');

        $response = $this->request($instanceId, $key)
            ->asJson()
            ->post("publish_api/v1/instances/{$instanceId}/publishes", [
                'interests' => $interests,
                'web' => [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                ],
            ]);

        return [
            'publish_id' => $response->json('publishId'),
            'status' => $response->status(),
        ];
    }

    protected function request(string $instanceId, ?string $key): PendingRequest
    {
        return \Illuminate\Support\Facades\Http::baseUrl(
            "https://{$instanceId}.pushnotifications.pusher.com"
        )
            ->withToken($key);
    }
}
