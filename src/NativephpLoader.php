<?php

namespace Atum\NativephpLoader;

class NativephpLoader
{
    /**
     * Execute the plugin functionality
     */
    public function execute(array $options = []): mixed
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('NativephpLoader.Execute', json_encode($options));

            if ($result) {
                $decoded = json_decode($result);
                return $decoded->data ?? null;
            }
        }

        return null;
    }

    /**
     * Get the current status
     */
    public function getStatus(): ?object
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('NativephpLoader.GetStatus', '{}');

            if ($result) {
                $decoded = json_decode($result);
                return $decoded->data ?? null;
            }
        }

        return null;
    }
}