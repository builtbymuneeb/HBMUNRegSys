<?php

namespace App\Models;

use App\Enums\PortfolioType;
use App\Support\Countries;
use Database\Factories\CommitteeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property PortfolioType $portfolio_type
 * @property list<string>|null $portfolios
 * @property bool $portfolios_public
 * @property string|null $agenda
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'portfolio_type', 'portfolios', 'portfolios_public', 'agenda', 'sort_order'])]
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
            'portfolios' => 'array',
            'portfolios_public' => 'boolean',
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

    /**
     * Delegates who have been allocated to this committee.
     *
     * @return HasMany<Delegate, $this>
     */
    public function allocatedDelegates(): HasMany
    {
        return $this->hasMany(Delegate::class, 'allocated_committee_id');
    }

    /**
     * The committee's sittings, earliest first.
     *
     * @return HasMany<CommitteeSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CommitteeSession::class)->orderBy('starts_at')->orderBy('id');
    }

    /**
     * The portfolios staff can allocate here, or null when the committee has no list yet
     * (a country committee then falls back to every country; a character one to free text).
     *
     * @return list<string>|null
     */
    public function portfolioOptions(): ?array
    {
        if ($this->portfolios) {
            return array_values($this->portfolios);
        }

        return $this->portfolio_type === PortfolioType::Country ? Countries::names() : null;
    }

    /**
     * The options delegates may pick from at registration, or null to let them type freely.
     *
     * @return list<string>|null
     */
    public function publicPortfolioOptions(): ?array
    {
        if ($this->portfolios && ! $this->portfolios_public) {
            return $this->portfolio_type === PortfolioType::Country ? Countries::names() : null;
        }

        return $this->portfolioOptions();
    }

    /**
     * The listed portfolio a loosely typed name refers to ("usa", "maryam nawaz"), if any.
     */
    public function matchPortfolio(?string $value): ?string
    {
        $options = $this->portfolioOptions();

        if ($options === null) {
            return filled($value) ? trim($value) : null;
        }

        return Countries::match($value, $options);
    }

    /**
     * "Country" or "Character", for labelling a delegate's portfolio.
     */
    public function portfolioLabel(): string
    {
        return $this->portfolio_type === PortfolioType::Character ? __('Character') : __('Country');
    }
}
