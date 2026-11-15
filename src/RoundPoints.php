<?php
namespace Funseco\Scoreboard;

use Illuminate\Database\Eloquent\Model;

/** @property int $player_id @property int $points */
class RoundPoints extends Model
{
    protected $table = 'scoreboard_round_points';
    protected $primaryKey = 'player_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
}
