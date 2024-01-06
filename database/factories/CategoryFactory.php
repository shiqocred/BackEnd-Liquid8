<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
 
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    { 
        return [];
    }

    public function fashion()
    {
        return $this->state(function (array $attributes) {
            return [
                'name_category' => 'fashion',
                'discount_category' => 60,
                'max_price_category' => 200000
            ];
        });
    }

    public function otomotif()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'otomotif',
                'discount_category' => 60,
                'max_price_category' => 200000
            ];
        });
    }

    public function toys_hobbies_a()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'toys_hobbies_a',
                'discount_category' => 60,
                'max_price_category' => 200000
            ];
        });
    }

    public function art()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'art',
                'discount_category' => 50,
                'max_price_category' => 200000
            ];
        });
    }

    public function toys_hobbies_b()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'toys_hobbies_b',
                'discount_category' => 50,
                'max_price_category' => 200000
            ];
        });
    }

    public function others_fmcg()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'others_fmcg',
                'discount_category' => 50,
                'max_price_category' => 200000
            ];
        });
    }
    public function elektronic_art()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'elektronic_art',
                'discount_category' => 50,
                'max_price_category' => 1000000
            ];
        });
    }

    public function mainan_hv()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'mainan_hv',
                'discount_category' => 50,
                'max_price_category' => 200000
            ];
        });
    }

    public function perlengkapan_bayi()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'perlengkapan_bayi',
                'discount_category' => 50,
                'max_price_category' => 200000
            ];
        });
    }
    
    public function beauty()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'beauty',
                'discount_category' => 50,
                'max_price_category' => 200000
            ];
        });
    }
    public function electronic_hv()
    {
        return $this->state(function (array $attributes){
            return [
                'name_category' => 'electronic_hv',
                'discount_category' => 50,
                'max_price_category' => 1000000
            ];
        });
    }





   

}
