<?php
declare(strict_types=1);

namespace Core;

final class RealtimeNotifier
{
    /**
     * Lightweight event hook placeholder.
     *
     * If your runtime defines a callable bridge (for example stellar_realtime_emit),
     * this method will forward events to it. Otherwise it is a no-op.
     */
    public static function emit(string $event, array $payload = []): void
    {
        $event = trim($event);
        if ($event === '') {
            return;
        }

        if (!function_exists('stellar_realtime_emit')) {
            return;
        }

        try {
            stellar_realtime_emit($event, $payload);
        } catch (\Throwable $e) {
            // Never block business flow for realtime side effects.
        }
    }
}
