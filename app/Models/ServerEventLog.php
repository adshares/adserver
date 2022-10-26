<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\ViewModel\ServerEventType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    public function getPropertiesAttribute(): array
    {
        return json_decode($this->attributes['properties'], true);
    }

    public function setPropertiesAttribute(array $properties): void
    {
        $this->attributes['properties'] = json_encode($properties, JSON_FORCE_OBJECT);
    }
}
