<?php

namespace Database\Seeders;

use App\Enums\PortfolioType;
use App\Models\Committee;
use App\Support\DefaultPortfolios;
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
            $committee = Committee::updateOrCreate(
                ['code' => $committee['code']],
                [...$committee, 'sort_order' => $sortOrder],
            );

            // Fill the starting portfolio list once; after that the team owns it.
            if ($committee->portfolios === null) {
                $committee->update([
                    'portfolios' => DefaultPortfolios::for($committee->code),
                    'portfolios_public' => ! in_array($committee->code, DefaultPortfolios::HIDDEN, true),
                ]);
            }
        }
    }
}
