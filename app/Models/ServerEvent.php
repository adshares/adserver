<?php

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon created_at
 * @property string type
 * @property array properties
 * @mixin Builder
 */
class ServerEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;
    protected $fillable = [
        'properties',
        'type',
    ];

    public static function register(string $type, array $properties = []): void
    {
        $event = new self();
        $event->type = $type;
        $event->properties = $properties;
        $event->save();
    }

    public static function fetchLatest(int $limit): Collection
    {
        return self::orderBy('created_at', 'desc')->limit($limit)->get();
    }

    public static function fetchLatestByType(string $type, int $limit): Collection
    {
        return self::where('type', $type)->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    public function getPropertiesAttribute(): array
    {
        return json_decode($this->attributes['properties'], true);
    }

    public function setPropertiesAttribute(array $properties): void
    {
        $this->attributes['properties'] = json_encode($properties, JSON_FORCE_OBJECT);
    }
}
