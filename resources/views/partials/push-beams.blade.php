{{-- Pusher Beams Web push setup --}}
{{-- Instance ID + device interest come from config/services.php (env: PUSHER_INSTANCE_ID, PUSHER_DEVICE_INTEREST) --}}
<script src="https://js.pusher.com/beams/2.1.0/push-notifications-cdn.js"></script>
<script>
    const beamsClient = new PusherPushNotifications.Client({
        instanceId: @json(config('services.pusher.instance_id')),
        serviceWorkerURL: '/service-worker.js',
        permissions: ['push', 'notifications'],
        serviceWorkerTransport: 'beams',
    });

    beamsClient.start()
        .then(() => beamsClient.addDeviceInterest(@json(config('services.pusher.device_interest', 'hello'))))
        .then(() => console.log('Successfully registered and subscribed!'))
        .catch(console.error);
</script>
