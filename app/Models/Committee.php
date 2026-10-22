<?php

namespace App\Models;

use App\Enums\PortfolioType;
use Database\Factories\CommitteeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property PortfolioType $portfolio_type
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'portfolio_type', 'sort_order'])]
class Committee extends Model
{
    /** @use HasFactory<CommitteeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portfolio_type' => PortfolioType::class,
        ];
    }

    /**
     * Order committees the way they are listed on the site.
     *
     * @param  Builder<Committee>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The label shown in dropdowns, e.g. "UN Women (UNW)".
     */
    public function label(): string
    {
        return "{$this->name} ({$this->code})";
    }
}
