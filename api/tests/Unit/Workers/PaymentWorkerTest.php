<?php

use App\Services\OrderService;
use App\Workers\PaymentWorker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

afterEach(fn () => Mockery::close());

function paymentMsg(string $eventType): AMQPMessage&\Mockery\MockInterface
{
    $msg = Mockery::mock(AMQPMessage::class);
    $msg->shouldReceive('get')->with('message_id')->andReturn('pay-evt-001');
    $msg->shouldReceive('get')->with('application_headers')->andThrow(new \OutOfBoundsException());
    $msg->shouldReceive('getBody')->andReturn(json_encode([
        'event_id'   => 'pay-evt-001',
        'event_type' => $eventType,
        'order_id'   => 'ord-pay-1',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload'    => ['status' => 'payment_pending'],
    ]));
    $msg->shouldReceive('ack');
    return $msg;
}

function makePaymentWorker(OrderService $service): PaymentWorker
{
    $channel = Mockery::mock(AMQPChannel::class);
    $redis   = Mockery::mock(Redis::class);
    $redis->shouldReceive('setex')->byDefault();

    $pdo    = Mockery::mock(PDO::class);
    $select = Mockery::mock(PDOStatement::class);
    $insert = Mockery::mock(PDOStatement::class);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/SELECT/'))->andReturn($select);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/INSERT/'))->andReturn($insert);
    $select->shouldReceive('execute')->andReturn(true);
    $select->shouldReceive('fetch')->andReturn(false);
    $insert->shouldReceive('execute')->andReturn(true);

    return new PaymentWorker($channel, $redis, $pdo, 'payment-worker-1', $service);
}

it('calls approvePayment when random result is in the 70% approval range', function () {
    $service = Mockery::mock(OrderService::class);
    $service->shouldReceive('approvePayment')->once()->with('ord-pay-1')->andReturn([]);
    $service->shouldReceive('refusePayment')->never();

    $worker = makePaymentWorker($service);
    $worker->forceApprove = true;
    $worker->process(paymentMsg('order.payment.pending'));
});

it('calls refusePayment when random result is in the 30% refusal range', function () {
    $service = Mockery::mock(OrderService::class);
    $service->shouldReceive('refusePayment')->once()->with('ord-pay-1')->andReturn([]);
    $service->shouldReceive('approvePayment')->never();

    $worker = makePaymentWorker($service);
    $worker->forceApprove = false;
    $worker->process(paymentMsg('order.payment.pending'));
});

it('ignores events with type other than order.payment.pending', function () {
    $service = Mockery::mock(OrderService::class);
    $service->shouldReceive('approvePayment')->never();
    $service->shouldReceive('refusePayment')->never();

    makePaymentWorker($service)->process(paymentMsg('order.created'));
});
