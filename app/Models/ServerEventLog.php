<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\ViewModel\ServerEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon created_at
 * @property ServerEventType type
 * @property array properties
 * @mixin Builder
 */
class ServerEventLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'type' => ServerEventType::class,
    ];

    protected $fillable = [
        'properties',
        'type',
    ];

    public static function register(ServerEventType $type, array $properties = []): void
    {
        $event = new self();
        $event->type = $type;
        $event->properties = $properties;
        $event->save();
    }

    public static function fetchLatest(array $types, int $limit = 10): Collection
    {
        $builder = self::orderBy('created_at', 'desc')->limit($limit);
        if ($types) {
            $builder->whereIn('type', $types);
        }
        return $builder->get();
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
