<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OrderIndexRequest;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    private const EAGER_LOAD = ['items', 'deliveryZone', 'store'];

    /**
     * Accepts both guests and authenticated customers — no auth:sanctum
     * middleware on this route, so the Bearer user (if any) is resolved
     * explicitly via the sanctum guard rather than the default 'web' one.
     */
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user('sanctum')?->id;

        $order = $orders->createOrder($data);

        // Toujours 201 : une commande rejouée via la même Idempotency-Key
        // doit produire un résultat fonctionnellement identique à la
        // création d'origine, pas un code différent selon qu'elle est
        // servie depuis la base ou fraîchement créée.
        return OrderResource::make($order->load(self::EAGER_LOAD))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authenticated customers only (auth:sanctum on the route) — always
     * scoped to the current user's own orders, never a global list and
     * never influenced by a client-supplied user_id.
     */
    public function index(OrderIndexRequest $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with(self::EAGER_LOAD)
            ->latest()
            ->paginate($request->validated('per_page') ?? 15);

        return OrderResource::collection($orders);
    }

    /**
     * Open to guests and authenticated customers, but only reveals an
     * order to its actual owner or to someone who can prove the exact
     * phone number used at checkout. Any other case returns 404 — never a
     * 403 — so a guessed order_number can't confirm another customer's
     * order even exists.
     */
    public function show(Request $request, string $orderNumber): OrderResource
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(self::EAGER_LOAD)
            ->first();

        if ($order === null || ! $this->canView($request, $order)) {
            abort(404);
        }

        return OrderResource::make($order);
    }

    private function canView(Request $request, Order $order): bool
    {
        $user = $request->user('sanctum');
        if ($user !== null && $order->user_id === $user->id) {
            return true;
        }

        $providedPhone = $request->header('X-Order-Phone');
        if ($providedPhone !== null && hash_equals((string) $order->customer_phone, (string) $providedPhone)) {
            return true;
        }

        return false;
    }
}
