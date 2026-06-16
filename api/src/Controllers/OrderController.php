<?php

namespace App\Controllers;

use App\Exceptions\InvalidTransitionException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Services\OrderService;

class OrderController
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly OrderService    $orderService,
        private readonly OrderRepository $orderRepo,
        private readonly EventRepository $eventRepo,
    )
    {
    }

    public function create(Request $request): Response
    {
        try {
            $request->validate([
                'customer_name' => 'required',
                'customer_email' => 'required',
                'items' => 'required',
            ]);

            $data = $request->json();

            if (empty($data['items'])) {
                throw new ValidationException(['items' => 'items must not be empty']);
            }

            if (!empty($data['idempotency_key'])) {
                $existing = $this->orderRepo->findByIdempotencyKey($data['idempotency_key']);
                if ($existing !== null) {
                    return Response::json($existing, 200);
                }
            }

            $data['total'] = array_sum(
                array_map(fn($item) => ($item['price'] ?? 0) * ($item['qty'] ?? 1), $data['items'])
            );

            $order = $this->orderService->createOrder($data);

            return Response::json($order, 201);

        } catch (ValidationException $e) {
            return Response::json(['errors' => $e->getErrors()], 422);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): Response
    {
        $filters = [];
        if ($request->get('status')) {
            $filters['status'] = $request->get('status');
        }

        $page = max(1, (int)($request->get('page', 1)));
        $perPage = max(1, min(100, (int)($request->get('per_page', 20))));

        $result = $this->orderRepo->findAll($filters, $page, $perPage);

        return Response::json($result, 200);
    }

    public function show(Request $request, string $id): Response
    {
        if (!preg_match(self::UUID_PATTERN, $id)) {
            return Response::json(['error' => 'Invalid UUID format'], 400);
        }

        $order = $this->orderRepo->findById($id);
        if ($order === null) {
            return Response::json(['error' => 'Order not found'], 404);
        }

        $events = $this->eventRepo->findByOrderId($id);

        return Response::json(array_merge($order, ['events' => $events]), 200);
    }

    public function pay(Request $request, string $id): Response
    {
        return $this->runTransition($id, fn() => $this->orderService->approvePayment($id));
    }

    private function runTransition(string $id, callable $transition): Response
    {
        if (!preg_match(self::UUID_PATTERN, $id)) {
            return Response::json(['error' => 'Invalid UUID format'], 400);
        }

        try {
            $order = $transition();
            return Response::json($order, 200);
        } catch (OrderNotFoundException $e) {
            return Response::json(['error' => $e->getMessage()], 404);
        } catch (InvalidTransitionException $e) {
            return Response::json(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function refusePayment(Request $request, string $id): Response
    {
        return $this->runTransition($id, fn() => $this->orderService->refusePayment($id));
    }

    public function cancel(Request $request, string $id): Response
    {
        return $this->runTransition($id, fn() => $this->orderService->cancel($id));
    }

    public function advance(Request $request, string $id): Response
    {
        return $this->runTransition($id, fn() => $this->orderService->advance($id));
    }
}
