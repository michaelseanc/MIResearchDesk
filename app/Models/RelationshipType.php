<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RelationshipType extends Model
{
    use BelongsToOrganization;

    /** Category options offered when creating a type inline. Opposition→red, Alignment→green on the graph. */
    public const CATEGORY_OPTIONS = [
        'employment' => 'Employment',
        'donation' => 'Donation',
        'board' => 'Board',
        'consultant' => 'Consultant',
        'legal' => 'Legal',
        'family' => 'Family',
        'project' => 'Project',
        'business' => 'Business',
        'financial' => 'Financial',
        'opposition' => 'Opposition (red)',
        'alignment' => 'Alignment (green)',
        'other' => 'Other',
    ];

    /** Badge color choices (Filament palette). Null = default (category-derived). */
    public const COLOR_OPTIONS = [
        'success' => 'Green',
        'danger' => 'Red',
        'warning' => 'Amber',
        'info' => 'Blue',
        'gray' => 'Gray',
    ];

    protected $fillable = ['name', 'label', 'is_directional', 'inverse_name', 'category', 'color'];

    protected $casts = ['is_directional' => 'boolean'];

    /** Resolve the badge color: explicit per-type color first, then a category default. */
    public function badgeColor(): string
    {
        return $this->color ?: match ($this->category) {
            'opposition' => 'danger',
            'alignment' => 'success',
            default => 'primary',
        };
    }

    /**
     * Create a type from an inline "+ Create" form, deriving a unique machine name from the label.
     */
    public static function createFromLabel(array $data): int
    {
        $base = Str::slug($data['label'], '_') ?: 'type';
        $name = $base;
        $i = 2;
        while (static::where('name', $name)->exists()) {
            $name = "{$base}_{$i}";
            $i++;
        }

        return static::create([
            'name' => $name,
            'label' => $data['label'],
            'inverse_name' => $data['inverse_name'] ?? null,
            'is_directional' => $data['is_directional'] ?? true,
            'category' => $data['category'] ?? null,
            'color' => $data['color'] ?? null,
        ])->getKey();
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(Relationship::class);
    }
}
