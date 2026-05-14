<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ReadModelRepository;
use App\Services\HeartbeatService;
use App\Services\MailpitService;
use App\Services\RabbitMqManagement;

class DashboardController
{
    public function __construct(
        private readonly ReadModelRepository $readModel,
        private readonly HeartbeatService    $heartbeat,
        private readonly RabbitMqManagement  $rabbitMq,
        private readonly ?MailpitService     $mailpit = null,
    ) {}

    public function stats(Request $request): Response
    {
        $workers = $this->heartbeat->getAll();

        $consumerStats = [
            'active'  => count(array_filter($workers, fn($w) => ($w['status'] ?? '') === 'active')),
            'idle'    => count(array_filter($workers, fn($w) => ($w['status'] ?? '') === 'idle')),
            'stopped' => count(array_filter($workers, fn($w) => ($w['status'] ?? '') === 'stopped')),
        ];

        return Response::json([
            'orders'    => $this->readModel->getOrderStats(),
            'events'    => $this->readModel->getEventStats(),
            'consumers' => $consumerStats,
        ]);
    }

    public function throughput(Request $request): Response
    {
        return Response::json($this->readModel->getThroughput());
    }

    public function funnel(Request $request): Response
    {
        return Response::json($this->readModel->getFunnel());
    }

    public function consumers(Request $request): Response
    {
        return Response::json($this->heartbeat->getAll());
    }

    public function eventFeed(Request $request): Response
    {
        $limit = max(1, min(200, (int) ($request->get('limit', 50))));
        return Response::json($this->readModel->getEventFeed($limit));
    }

    public function eventsByType(Request $request): Response
    {
        return Response::json($this->readModel->getEventsByType());
    }

    public function queues(Request $request): Response
    {
        return Response::json($this->rabbitMq->getQueues());
    }

    public function notifications(Request $request): Response
    {
        if ($this->mailpit === null) {
            return Response::json(['error' => 'Mailpit not configured'], 503);
        }

        $id = $request->get('id', '');

        if ($id !== '') {
            $message = $this->mailpit->getMessage($id);
            return $message !== null
                ? Response::json($message)
                : Response::json(['error' => 'Message not found'], 404);
        }

        $limit = max(1, min(200, (int) $request->get('limit', 50)));
        return Response::json($this->mailpit->getMessages($limit));
    }
}
