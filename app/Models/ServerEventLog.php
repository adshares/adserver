<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\ViewModel\ServerEventType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property Carbon created_at
 * @property ServerEventType type
 * @property array properties
 */
class ServerEventLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'created_at' => 'date:' . DateTimeInterface::ATOM,
        'type' => ServerEventType::class,
    ];

    protected $fillable = [
        'properties',
        'type',
    ];

    protected $visible = [
        'id',
        'created_at',
        'type',
        'properties',
    ];

    public static function register(ServerEventType $type, array $properties = []): void
    {
        $event = new self();
        $event->type = $type;
        $event->properties = $properties;
        $event->save();
    }

    public static function getBuilderForFetching(
        array $types = [],
        DateTimeInterface $from = null,
        DateTimeInterface $to = null,
    ): Builder {
        $builder = self::orderBy('id', 'desc');
        if ($types) {
            $builder->whereIn('type', $types);
        }
        if (null !== $from) {
            $builder->where('created_at', '>=', $from);
        }
        if (null !== $to) {
            $builder->where('created_at', '<=', $to);
        }
        return $builder;
    }

    public static function getBuilderForFetchingLatest(array $types = []): Builder
    {
        $latestEvents = self::select(DB::raw('MAX(id) as id'))
            ->groupBy('type');
        if ($types) {
            $latestEvents->whereIn('type', $types);
        }

        return self::select(DB::raw('s.*'))
            ->from('server_event_logs AS s')
            ->orderBy('id', 'desc')
            ->joinSub($latestEvents, 'le', function ($join) {
                $join->on('s.id', '=', 'le.id');
            });
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
