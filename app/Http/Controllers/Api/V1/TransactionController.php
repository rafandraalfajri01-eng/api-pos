<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetTransactionsRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionController extends Controller
{
    public function index(GetTransactionsRequest $request)
    {
        $transactions = Transaction::with('customer')
            ->when($request->search, function ($query, $search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($transactions, TransactionResource::class),
            'transactions list'
        );
    }

    public function store(StoreTransactionRequest $request)
    {
        try {
            $transaction = DB::transaction(function () use ($request) {
                $subtotal = 0;
                $items = [];

                foreach ($request->validated('items') as $item) {
                    $product = Product::query()
                        ->lockForUpdate()
                        ->find($item['product_id']);

                    if (! $product || $product->stock < $item['quantity']) {
                        $productName = $product?->name ?? 'Product';

                        throw new \RuntimeException(
                            "Insufficient stock for {$productName}",
                            Response::HTTP_UNPROCESSABLE_ENTITY
                        );
                    }

                    $itemSubtotal = round((float) $product->price * $item['quantity'], 2);
                    $subtotal += $itemSubtotal;
                    $items[] = [
                        'product' => $product,
                        'price' => $product->price,
                        'quantity' => $item['quantity'],
                        'subtotal' => $itemSubtotal,
                    ];
                }

                $tax = round((float) ($request->validated('tax') ?? 0), 2);
                $total = round($subtotal + $tax, 2);

                $transaction = Transaction::create([
                    'code' => 'TRX-'.now()->format('YmdHis').'-'.strtoupper(substr(uniqid(), -6)),
                    'customer_id' => $request->validated('customer_id'),
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                ]);

                foreach ($items as $item) {
                    $transaction->items()->create([
                        'product_id' => $item['product']->id,
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                    ]);

                    $item['product']->decrement('stock', $item['quantity']);
                }

                return $transaction->load(['customer', 'items.product']);
            });

            return ApiResponse::success(
                new TransactionResource($transaction),
                'transaction created',
                Response::HTTP_CREATED
            );
        } catch (Throwable $exception) {
            report($exception);

            if ($exception->getCode() === Response::HTTP_UNPROCESSABLE_ENTITY) {
                return ApiResponse::error(
                    $exception->getMessage(),
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            return ApiResponse::error(
                'Transaction could not be created',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show(string $id)
    {
        try {
            $transaction = Transaction::with(['customer', 'items.product'])->find($id);

            if (! $transaction) {
                return ApiResponse::error(
                    'Transaction not found',
                    Response::HTTP_NOT_FOUND
                );
            }

            return ApiResponse::success(
                new TransactionResource($transaction),
                'transaction details'
            );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                'Transaction could not be retrieved',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
