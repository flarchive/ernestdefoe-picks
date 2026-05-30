<?php

namespace Resofire\Picks;

use Flarum\Database\AbstractModel;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property int         $year
 * @property string|null $start_date
 * @property string|null $end_date
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Season extends AbstractModel
{
    public $timestamps = true;

    protected $table = 'picks_seasons';

    protected $fillable = [
        'name',
        'slug',
        'year',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function weeks()
    {
        return $this->hasMany(Week::class);
    }

    public function userScores()
    {
        return $this->hasMany(UserScore::class);
    }
}
