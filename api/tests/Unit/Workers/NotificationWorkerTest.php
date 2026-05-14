<?php

use App\Mail\MailerInterface;
use App\Workers\NotificationWorker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

afterEach(fn () => Mockery::close());

function notifMsg(string $eventType): AMQPMessage&\Mockery\MockInterface
{
    $msg = Mockery::mock(AMQPMessage::class);
    $msg->shouldReceive('get')->with('message_id')->andReturn('notif-evt-001');
    $msg->shouldReceive('get')->with('application_headers')->andThrow(new \OutOfBoundsException());
    $msg->shouldReceive('getBody')->andReturn(json_encode([
        'event_id'   => 'notif-evt-001',
        'event_type' => $eventType,
        'order_id'   => 'ord-notif-1',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload'    => [
            'customer_email' => 'joao@exemplo.com',
            'customer_name'  => 'João Silva',
            'total'          => 549.90,
            'status'         => ltrim(str_replace('order.', '', $eventType), '.'),
        ],
    ]));
    $msg->shouldReceive('ack');
    return $msg;
}

function makeNotifWorker(MailerInterface $mailer): NotificationWorker
{
    $channel = Mockery::mock(AMQPChannel::class);
    $redis   = Mockery::mock(Redis::class);
    $redis->shouldReceive('get')->andReturn(false)->byDefault();
    $redis->shouldReceive('setex')->byDefault();

    $pdo    = Mockery::mock(PDO::class);
    $select = Mockery::mock(PDOStatement::class);
    $insert = Mockery::mock(PDOStatement::class);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/SELECT/'))->andReturn($select);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/INSERT/'))->andReturn($insert);
    $select->shouldReceive('execute')->andReturn(true);
    $select->shouldReceive('fetch')->andReturn(false);
    $insert->shouldReceive('execute')->andReturn(true);

    return new NotificationWorker($channel, $redis, $pdo, 'notification-worker-1', $mailer);
}

$emailCases = [
    ['order.created',          'Pedido recebido'],
    ['order.payment.approved', 'Pagamento confirmado'],
    ['order.payment.refused',  'Pagamento recusado'],
    ['order.shipped',          'Pedido enviado'],
    ['order.delivered',        'Pedido entregue'],
    ['order.cancelled',        'Pedido cancelado'],
];

foreach ($emailCases as [$eventType, $expectedSubject]) {
    it("sends email to customer with correct subject for {$eventType}", function () use ($eventType, $expectedSubject) {
        $mailer = Mockery::mock(MailerInterface::class);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (string $to, string $subject) use ($expectedSubject) {
                return $to === 'joao@exemplo.com'
                    && str_contains($subject, $expectedSubject);
            });

        makeNotifWorker($mailer)->process(notifMsg($eventType));
    });
}
