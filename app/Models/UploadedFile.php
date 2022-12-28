<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\Ownership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\UuidInterface;

/**
 * @property int id
 * @property Carbon created_at
 * @property Carbon|null deleted_at
 * @property string $uuid
 * @property string medium
 * @property string|null vendor
 * @property string mime
 * @property string|null $size
 * @property string content
 *
 * @mixin Builder
 */
class UploadedFile extends Model
{
    use AutomateMutators;
    use BinHex;
    use HasFactory;
    use Ownership;

    public $timestamps = false;

    protected $dates = [
        'created_at',
        'deleted_at',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $fillable = [
        'medium',
        'vendor',
        'mime',
        'size',
        'content',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    public static function fetchByUuidOrFail(UuidInterface $uuid): self
    {
        $file = (new UploadedFile())->where('uuid', $uuid->getBytes())->first();
        if (null === $file) {
            throw new ModelNotFoundException(sprintf('No query results for file %s', $uuid));
        }
        return $file;
    }
}
