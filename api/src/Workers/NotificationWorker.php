<?php

namespace App\Workers;

use App\Mail\MailerInterface;
use PhpAmqpLib\Channel\AMQPChannel;

class NotificationWorker extends BaseWorker
{
    private const SUBJECTS = [
        'order.created' => 'Pedido recebido',
        'order.payment.approved' => 'Pagamento confirmado',
        'order.payment.refused' => 'Pagamento recusado',
        'order.shipped' => 'Pedido enviado',
        'order.delivered' => 'Pedido entregue',
        'order.cancelled' => 'Pedido cancelado',
    ];

    public function __construct(
        AMQPChannel                      $channel,
        \Redis                           $redis,
        \PDO                             $pdo,
        string                           $workerId,
        private readonly MailerInterface $mailer,
    )
    {
        parent::__construct($channel, $redis, $pdo, $workerId);
    }

    protected function handle(array $event): void
    {
        $type = $event['event_type'];

        if (!isset(self::SUBJECTS[$type])) {
            return;
        }

        $payload = $event['payload'] ?? [];
        $to = $payload['customer_email'] ?? '';
        $name = $payload['customer_name'] ?? '';

        $subject = self::SUBJECTS[$type];
        $body = "Olá, {$name}.\n\n{$subject} — pedido #{$event['order_id']}.";

        $this->mailer->send($to, $subject, $body);
    }
}
