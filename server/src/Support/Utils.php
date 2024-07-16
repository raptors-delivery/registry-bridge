<?php

namespace Fleetbase\RegistryBridge\Support;

use Stripe\StripeClient;

class Utils
{
    /**
     * Get the StripeClient instance.
     */
    public static function getStripeClient(array $options = []): ?StripeClient
    {
        return new StripeClient([
            'api_key' => config('registry-bridge.stripe.secret'),
            ...$options,
        ]);
    }
}
