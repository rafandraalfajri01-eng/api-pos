<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_category_id' => $this->product_category_id,
            'image' => $this->image ? asset(Storage::url($this->image)) : null,
            'name' => $this->name,
            'price' => (float) (string) $this->price,
            'stock' => $this->stock,
        ];
    }
}
