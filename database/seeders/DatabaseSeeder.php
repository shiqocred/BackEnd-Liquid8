<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\Category;
use App\Models\Color_tag;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        Category::factory()->fashion()->create();
        Category::factory()->otomotif()->create();
        Category::factory()->toys_hobbies_a()->create();
        Category::factory()->art()->create();
        Category::factory()->toys_hobbies_b()->create();
        Category::factory()->others_fmcg()->create();
        Category::factory()->elektronic_art()->create();
        Category::factory()->mainan_hv()->create();
        Category::factory()->perlengkapan_bayi()->create();
        Category::factory()->beauty()->create();
        Category::factory()->electronic_hv()->create();
        Color_tag::factory()->merah()->create();
        Color_tag::factory()->biru()->create();
        
      
        $users = Role::factory();
        foreach (range(0, 5) as $index) {
            $users->role($index)->create();
        };

        $users = User::factory();
        foreach (range(0, 5) as $index) {
            $users->account($index)->create();
        };
        


    }
}
