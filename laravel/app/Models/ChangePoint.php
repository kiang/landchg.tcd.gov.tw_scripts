<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ChangePoint extends Model
{
    protected $fillable = [
        'point_id',
        'authority',
        'county_city',
        'verification_result',
        'change_type',
        'year',
        'latitude',
        'longitude',
    ];

    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $radiusMeters): Builder
    {
        return $query
            ->whereRaw(
                'ST_DWithin(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                [$lng, $lat, $radiusMeters]
            )
            ->selectRaw(
                '*, ST_Distance(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance',
                [$lng, $lat]
            );
    }
}
