{{-- Laravel Echo real-time notification listener for employees --}}
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<script>
(function () {
    @auth
    const KEY = @json(config('broadcasting.default') === 'pusher' ? config('services.pusher.key') : null);
    const CLUSTER = @json(config('services.pusher.options.cluster') ?? 'mt1');
    const USER_ID = @json(auth()->id());

    if (!KEY || !window.Pusher) return;

    const pusher = new window.Pusher(KEY, {
        cluster: CLUSTER,
        encrypted: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    const channel = pusher.subscribe('private-user.' + USER_ID + '.notifications');
    channel.bind('notification.received', function (payload) {
        window.dispatchEvent(
            new CustomEvent('leadx:notification', { detail: payload })
        );
    });
    @endauth
})();
</script>
