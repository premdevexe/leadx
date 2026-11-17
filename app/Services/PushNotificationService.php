<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Pusher\PushNotifications\PushNotifications;

class PushNotificationService
{
    protected ?PushNotifications $beamsClient = null;

    public function __construct()
    {
        $instanceId = config('services.pusher_beams.instance_id');
        $secretKey  = config('services.pusher_beams.secret_key');

        if (! empty($instanceId) && ! empty($secretKey)) {
            try {
                $this->beamsClient = new PushNotifications([
                    'instanceId' => $instanceId,
                    'secretKey'  => $secretKey,
                ]);
            } catch (Exception $e) {
                Log::warning('Pusher Beams initialization error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Check if Pusher Beams client is initialized.
     */
    public function isConfigured(): bool
    {
        return $this->beamsClient !== null;
    }

    /**
     * Set a custom or mock Pusher client (useful for unit testing).
     */
    public function setClient(PushNotifications $client): self
    {
        $this->beamsClient = $client;

        return $this;
    }

    /**
     * Publish notification to one or more interests (e.g. ['hello', 'all'])
     *
     * @param array $interests
     * @param string $title
     * @param string $body
     * @param array $data Additional payload data for deep linking
     * @return string|null publishId
     * @throws Exception
     */
    public function sendToInterests(array $interests, string $title, string $body, array $data = []): ?string
    {
        if (! $this->beamsClient) {
            throw new Exception('Pusher Beams credentials are not configured. Please check PUSHER_BEAMS_INSTANCE_ID and PUSHER_BEAMS_SECRET_KEY.');
        }

        try {
            $response = $this->beamsClient->publishToInterests(
                $interests,
                [
                    'fcm' => [
                        'notification' => [
                            'title' => $title,
                            'body'  => $body,
                            'sound' => 'default',
                        ],
                        'data' => array_merge($data, [
                            'title'     => $title,
                            'body'      => $body,
                            'timestamp' => now()->toISOString(),
                        ]),
                    ],
                ]
            );

            return $response->publishId ?? null;
        } catch (Exception $e) {
            Log::error('Pusher Beams Publish Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Publish notification to specific authenticated users
     *
     * @param array $userIds
     * @param string $title
     * @param string $body
     * @param array $data
     * @return string|null publishId
     * @throws Exception
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): ?string
    {
        if (! $this->beamsClient) {
            throw new Exception('Pusher Beams credentials are not configured. Please check PUSHER_BEAMS_INSTANCE_ID and PUSHER_BEAMS_SECRET_KEY.');
        }

        try {
            $response = $this->beamsClient->publishToUsers(
                $userIds,
                [
                    'fcm' => [
                        'notification' => [
                            'title' => $title,
                            'body'  => $body,
                            'sound' => 'default',
                        ],
                        'data' => $data,
                    ],
                ]
            );

            return $response->publishId ?? null;
        } catch (Exception $e) {
            Log::error('Pusher Beams Publish to Users Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
