<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds the sample content.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ArticleSeeder::class);
    }
}
