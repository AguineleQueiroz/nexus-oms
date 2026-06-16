<?php

namespace App\Workers;

class InventoryWorker extends BaseWorker
{
    protected function handle(array $event): void
    {
        match ($event['event_type']) {
            'order.created' => $this->reserveStock($event),
            'order.picking' => $this->deductStock($event),
            'order.cancelled' => $this->releaseStock($event),
            default => null,
        };
    }

    private function reserveStock(array $event): void
    {
        // Stock reservation simulation — integrate with real inventory system here
    }

    private function deductStock(array $event): void
    {
        // Stock deduction simulation
    }

    private function releaseStock(array $event): void
    {
        // Stock release simulation
    }
}
