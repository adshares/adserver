<?php

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property Carbon created_at
 * @property Carbon|null deleted_at
 * @property string ulid
 * @property string medium
 * @property string|null vendor
 * @property string mime
 * @property string|null scope
 * @property string content
 *
 * @mixin Builder
 */
class UploadedFile extends Model
{
    use HasFactory;
    use HasUlids;

    public $timestamps = false;

    protected $dates = [
        'created_at',
        'deleted_at',
    ];

    protected $fillable = [
        'medium',
        'vendor',
        'mime',
        'scope',
        'content',
    ];

    public static function fetchByUlidOrFail(string $ulid): self
    {
        $file = (new UploadedFile())->where('ulid', $ulid)->first();
        if (null === $file) {
            throw new ModelNotFoundException(sprintf('No query results for file %s', $ulid));
        }
        return $file;
    }

    public function uniqueIds(): array
    {
        return [
            'ulid',
        ];
    }
}
