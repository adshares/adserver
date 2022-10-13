<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\ViewModel\ServerEventType;
use DateTimeInterface;
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

    public static function fetch(
        array $types = [],
        DateTimeInterface $from = null,
        DateTimeInterface $to = null,
        int $limit = 10
    ): Collection {
        $builder = self::orderBy('created_at', 'desc')->limit($limit);
        if ($types) {
            $builder->whereIn('type', $types);
        }
        if (null !== $from) {
            $builder->where('created_at', '>=', $from);
        }
        if (null !== $to) {
            $builder->where('created_at', '<=', $to);
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
