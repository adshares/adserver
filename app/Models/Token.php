<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\Serialize;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use AutomateMutators;
    use BinHex;
    use Serialize;

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'multi_usage', 'payload', 'tag', 'user_id', 'valid_until',
    ];

    /**
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'payload' => 'Serialize',
    ];

    /**
     * Checks if can generate another token for user based on time limit and tag.
     *
     * @param int    $user_id
     * @param string $tag
     * @param int    $older_then_seconds
     *
     * @return bool
     */
    public static function canGenerate(int $user_id, $tag, int $older_then_seconds)
    {
        if (self::where('user_id', $user_id)->where('tag', $tag)->where('created_at', '>', date('Y-m-d H:i:s', time() - $older_then_seconds))->count()) {
            return false;
        }

        return true;
    }

    /**
     * checks if token is valid, process it (removes if one time use only, extends if multi use and extension requested, returns array from token data).
     *
     * @param string $uuid
     * @param int    $user_id
     * @param int    $extend_valid_until_seconds
     *
     * @return array
     */
    public static function check($uuid, int $user_id = null, int $extend_valid_until_seconds = null)
    {
        $q = self::where('uuid', hex2bin($uuid))->where('valid_until', '>', date('Y-m-d H:i:s'));
        if (!empty($userId)) {
            $q->where('user_id', $user_id);
        }
        $token = $q->first();
        if (empty($token)) {
            return false;
        }
        if (!$token->multi_usage) {
            $return = $token->toArray();
            $token->delete();

            return $return;
        }
        if (!empty($valid_until_seconds)) {
            $token->valid_until = date('Y-m-d H:i:s', time() + $valid_until_seconds);
            $token->save();
        }

        return $token->toArray();
    }

    /**
     * generates Token and returns token uuid.
     *
     * @param string $tag
     * @param int    $valid_until_seconds
     * @param int    $user_id
     * @param mixed  $payload
     * @param bool   $multi_usage
     *
     * @return string
     */
    public static function generate(string $tag, int $valid_until_seconds, int $user_id = null, $payload = null, bool $multi_usage = false)
    {
        $valid_until = date('Y-m-d H:i:s', time() + $valid_until_seconds);
        $token = self::create(compact('user_id', 'tag', 'payload', 'valid_until', 'multi_usage'));

        return $token->uuid;
    }
}
