<?php

namespace App\Models;

use App\Enums\GoldPurity;
use Database\Factories\GoldRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An append-only gold rate record.
 *
 * Historical rates must NEVER be overwritten: future payment calculations
 * depend on the rate that applied at the time of payment. Therefore:
 *  - rate values (date, time, purity, rates, source) cannot be updated;
 *  - records cannot be deleted;
 *  - the only permitted change is status (deactivating a mistaken rate).
 * Every new rate is a new row.
 *
 * @property int $id
 * @property Carbon $rate_date
 * @property string|null $rate_time
 * @property GoldPurity $purity
 * @property string $rate_per_gram
 * @property string $rate_per_10_gram
 * @property string|null $source
 * @property bool $status
 */
class GoldRate extends Model
{
    /** @use HasFactory<GoldRateFactory> */
    use HasFactory;

    /** Source tag for development / sample rows — never real Sri Sindhu Jewellery rates. */
    public const SOURCE_DEVELOPMENT = 'development-seed';

    /** Columns that form the immutable rate history. */
    private const IMMUTABLE = ['rate_date', 'rate_time', 'purity', 'rate_per_gram', 'rate_per_10_gram', 'source'];

    protected $fillable = [
        'rate_date',
        'rate_time',
        'purity',
        'rate_per_gram',
        'rate_per_10_gram',
        'source',
        'status',
    ];

    protected $attributes = [
        'status' => true,
    ];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate_time' => 'string', // HH:MM:SS — Laravel has no time-only cast; kept as a plain string.
            'purity' => GoldPurity::class,
            'rate_per_gram' => 'decimal:2',
            'rate_per_10_gram' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GoldRate $rate) {
            if ($rate->rate_per_10_gram === null && $rate->rate_per_gram !== null) {
                $rate->rate_per_10_gram = round((float) $rate->rate_per_gram * 10, 2);
            }
        });

        static::updating(function (GoldRate $rate) {
            $changed = array_intersect(array_keys($rate->getDirty()), self::IMMUTABLE);

            if ($changed !== []) {
                throw new LogicException('Gold rate history cannot be overwritten ('.implode(', ', $changed).'). Create a new rate instead.');
            }
        });

        static::deleting(function () {
            throw new LogicException('Gold rate history cannot be deleted. Deactivate the rate instead.');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeForPurity(Builder $query, GoldPurity|string $purity): Builder
    {
        return $query->where('purity', $purity instanceof GoldPurity ? $purity->value : $purity);
    }

    /**
     * Newest first: by date, then time, then insertion order.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('rate_date')->orderByDesc('rate_time')->orderByDesc('id');
    }

    /**
     * The most recent active rate for a purity, or null when none exists.
     */
    public static function latestFor(GoldPurity|string $purity): ?self
    {
        return static::query()->active()->forPurity($purity)->latestFirst()->first();
    }

    /**
     * The active rate in effect at a given moment (for future payment calculations).
     */
    public static function effectiveAt(GoldPurity|string $purity, Carbon $moment): ?self
    {
        return static::query()->active()->forPurity($purity)
            ->where(function (Builder $query) use ($moment) {
                $query->whereDate('rate_date', '<', $moment->toDateString())
                    ->orWhere(function (Builder $sameDay) use ($moment) {
                        $sameDay->whereDate('rate_date', $moment->toDateString())
                            ->where(fn (Builder $time) => $time->whereNull('rate_time')->orWhere('rate_time', '<=', $moment->format('H:i:s')));
                    });
            })
            ->latestFirst()
            ->first();
    }

    public function isDevelopmentData(): bool
    {
        return $this->source === self::SOURCE_DEVELOPMENT;
    }

    /**
     * The moment the rate took effect (date + optional time).
     */
    public function effectiveAtMoment(): Carbon
    {
        return $this->rate_date->copy()->setTimeFromTimeString($this->rate_time ?? '00:00:00');
    }
}
