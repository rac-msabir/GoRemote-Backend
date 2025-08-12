<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Full-Stack Programming', 'Back-End Programming', 'Front-End Programming',
            'DevOps', 'Data', 'Design', 'Product', 'Marketing', 'Sales', 'Customer Support',
            'HR', 'Finance', 'Engineering',
        ];
        foreach ($categories as $name) {
            DB::table('categories')->updateOrInsert(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $skills = [
            'Laravel', 'PHP', 'Vue', 'React', 'Node', 'Docker', 'Kubernetes', 'AWS',
            'MySQL', 'PostgreSQL', 'Redis', 'Tailwind', 'CI/CD', 'REST', 'GraphQL',
            'ADP', 'Client Relationship Management',
        ];
        foreach ($skills as $name) {
            DB::table('skills')->updateOrInsert(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}


