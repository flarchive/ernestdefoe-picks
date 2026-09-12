<?php

namespace Resofire\Picks;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

/**
 * @property int         $id
 * @property int         $user_id
 * @property int         $event_id
 * @property string      $selected_outcome
 * @property bool|null   $is_correct
 * @property int|null    $confidence
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Pick extends AbstractModel
{
    public $timestamps = true;

    protected $table = 'picks_picks';

    protected $fillable = [
        'user_id',
        'event_id',
        'selected_outcome',
        'is_correct',
        'confidence',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'confidence' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(PickEvent::class, 'event_id');
    }

    public function isCorrect(): bool
    {
        return $this->is_correct === true;
    }

    public function isIncorrect(): bool
    {
        return $this->is_correct === false;
    }

    public function isPending(): bool
    {
        return $this->is_correct === null;
    }

    public function canBeChanged(): bool
    {
        return $this->event && $this->event->canPick();
    }
}
