<?php

namespace Database\Seeders;

use App\Enums\PortfolioType;
use App\Models\Committee;
use Illuminate\Database\Seeder;

class CommitteeSeeder extends Seeder
{
    /**
     * Seed the conference's committees.
     */
    public function run(): void
    {
        $committees = [
            ['code' => 'UNW', 'name' => 'United Nations Women', 'portfolio_type' => PortfolioType::Country],
            ['code' => 'UNSC', 'name' => 'United Nations Security Council', 'portfolio_type' => PortfolioType::Country],
            ['code' => 'PNA', 'name' => 'Pakistan National Assembly', 'portfolio_type' => PortfolioType::Character],
            ['code' => 'SPECIAL', 'name' => 'Special Committee', 'portfolio_type' => PortfolioType::Character],
        ];

        foreach ($committees as $sortOrder => $committee) {
            Committee::updateOrCreate(
                ['code' => $committee['code']],
                [...$committee, 'sort_order' => $sortOrder],
            );
        }
    }
}
