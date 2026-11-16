<?php

namespace Tests;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Custom HttpFactory that intercepts Pusher Beams calls as a fallback
        // so automated tests run offline with sub-second execution times,
        // while preserving test-specific Http::fake() mappings and their ordering.
        $dispatcher = app()->bound(Dispatcher::class) ? app(Dispatcher::class) : null;
        $factory = new class($dispatcher) extends HttpFactory {
            public function createPendingRequest()
            {
                return tap($this->newPendingRequest(), function ($request) {
                    $fallback = function ($req) {
                        if (str_contains($req->url(), 'pushnotifications.pusher.com')) {
                            return HttpFactory::response([
                                'publishId' => 'pub-test-fake-id',
                            ], 200);
                        }

                        return null;
                    };

                    $stubs = ($this->stubCallbacks ?? new Collection)->concat([$fallback]);

                    $request
                        ->stub($stubs)
                        ->preventStrayRequests($this->preventStrayRequests)
                        ->allowStrayRequests($this->allowedStrayRequestUrls);
                });
            }
        };

        app()->instance('http', $factory);
        Http::swap($factory);
    }
}
