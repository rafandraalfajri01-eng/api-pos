<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(GetProductsRequest $request)
    {
        $products = Product::search($request->search)
            ->latest()
            ->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($products, ProductResource::class),
            'products list'
        );
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return ApiResponse::success(
            new ProductResource($product),
            'product created',
            Response::HTTP_CREATED
        );
    }

    public function show(string $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return ApiResponse::error(
                'Product not found',
                Response::HTTP_NOT_FOUND
            );
        }

        return ApiResponse::success(
            new ProductResource($product),
            'product details'
        );
    }

    public function update(UpdateProductRequest $request, string $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return ApiResponse::error(
                'Product not found',
                Response::HTTP_NOT_FOUND
            );
        }

        $product->update($request->validated());

        return ApiResponse::success(
            new ProductResource($product),
            'product updated successfully'
        );
    }

    public function destroy(string $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return ApiResponse::error(
                'Product not found',
                Response::HTTP_NOT_FOUND
            );
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return ApiResponse::success(
            null,
            'product deleted successfully'
        );
    }
}
