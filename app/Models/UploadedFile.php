<?php

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
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

    public function uniqueIds(): array
    {
        return ['ulid'];
    }
}
