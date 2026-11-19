<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ShopHours
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Whether the shop accepts new orders right now.
     */
    public function isAcceptingOrders(?CarbonImmutable $at = null): bool
    {
        if (! $this->settings->bool('ordering.enabled')) {
            return false;
        }

        $at ??= CarbonImmutable::now();
        $open = $at->setTimeFromTimeString($this->settings->string('hours.open'));
        $lastOrder = $at->setTimeFromTimeString($this->settings->string('hours.last_order'));

        // Shops that close after midnight (e.g. 18:00 – 02:00).
        if ($lastOrder->lessThanOrEqualTo($open)) {
            return $at->greaterThanOrEqualTo($open) || $at->lessThanOrEqualTo($lastOrder);
        }

        return $at->betweenIncluded($open, $lastOrder);
    }

    public function closedMessage(): string
    {
        if (! $this->settings->bool('ordering.enabled')) {
            return 'Online ordering is paused at the moment. Please check back shortly.';
        }

        return sprintf(
            'We take orders from %s until %s. See you then!',
            $this->formatTime($this->settings->string('hours.open')),
            $this->formatTime($this->settings->string('hours.last_order')),
        );
    }

    /**
     * @return array{open: string, close: string, last_order: string}
     */
    public function toArray(): array
    {
        return [
            'open' => $this->formatTime($this->settings->string('hours.open')),
            'close' => $this->formatTime($this->settings->string('hours.close')),
            'last_order' => $this->formatTime($this->settings->string('hours.last_order')),
        ];
    }

    private function formatTime(string $time): string
    {
        return CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A');
    }
}
