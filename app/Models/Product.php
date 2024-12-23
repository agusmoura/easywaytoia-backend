<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'description',
        'type',
        'is_active',
        'price',
        'stripe_price_id',
        'related_products',
        'success_page'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'related_products' => 'array'
    ];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function childProducts()
    {
        return $this->belongsToMany(
            Product::class,
            'product_relationships',
            'parent_product_id',
            'child_product_id'
        )->withTimestamps();
    }

    public function parentProducts()
    {
        return $this->belongsToMany(
            Product::class,
            'product_relationships',
            'child_product_id',
            'parent_product_id'
        )->withTimestamps();
    }

    public static function createProduct(array $data)
    {
        $validator = Validator::make($data, [
            'identifier' => ['required', 'string', 'max:255', Rule::unique('products')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['course', 'bundle', 'maximizer'])],
            'stripe_price_id' => ['required', 'string'],
            'is_active' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0'],
            'success_page' => ['nullable', 'string'],
            'related_products' => ['nullable', 'array'],
            'related_products.*' => ['string', 'exists:products,identifier']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $product = self::create($data);

        // Si es un bundle o maximizador, establecer las relaciones con otros productos
        if (in_array($data['type'], ['bundle', 'maximizer']) && !empty($data['related_products'])) {
            $relatedProducts = self::whereIn('identifier', $data['related_products'])->get();
            $product->childProducts()->attach($relatedProducts->pluck('id'));
        }

        return $product;
    }

    public function updateProduct(array $data)
    {
        $validator = Validator::make($data, [
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => [Rule::in(['course', 'bundle', 'maximizer'])],
            'stripe_price_id' => ['string'],
            'is_active' => ['boolean'],
            'price' => ['numeric', 'min:0'],
            'success_page' => ['nullable', 'string'],
            'related_products' => ['nullable', 'array'],
            'related_products.*' => ['string', 'exists:products,identifier']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $this->update($data);

        // Actualizar relaciones si es necesario
        if (isset($data['related_products']) && in_array($this->type, ['bundle', 'maximizer'])) {
            $relatedProducts = self::whereIn('identifier', $data['related_products'])->get();
            $this->childProducts()->sync($relatedProducts->pluck('id'));
        }

        return $this;
    }
} 