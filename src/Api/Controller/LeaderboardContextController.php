<?php

namespace Resofire\Picks\Api\Controller;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Capsule\Manager as DB;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /picks/leaderboard-context
 *
 * Returns the current leaderboard context so the frontend knows whether
 * the season is active, in off-season retention, or fully off-season.
 *
 * Off-season retention: all games are finished but the season ended within
 * 30 days — Week and Season scopes remain visible with final standings.
 *
 * After 30 days: off_season = true, retention_expired = true.
 * Week and Season scopes show an off-season empty state.
 *
 * Permission: picks.view
 */
class LeaderboardContextController implements RequestHandlerInterface
{
    protected const RETENTION_DAYS = 30;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('picks.view');

        try {
            // ── Is the season currently active? ───────────────────────────────
            // A season is active if any week has unfinished (scheduled/in_progress) games.
            // Find the most recent season with unfinished games, scoped so that
            // future unplayed weeks in later seasons don't interfere.
            $activeSeason = DB::table('picks_seasons')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('picks_weeks')
                      ->join('picks_events', 'picks_events.week_id', '=', 'picks_weeks.id')
                      ->whereColumn('picks_weeks.season_id', 'picks_seasons.id')
                      ->whereIn('picks_events.status', ['scheduled', 'in_progress']);
                })
                ->orderByDesc('year')
                ->first();

            $activeWeek = null;
            if ($activeSeason) {
                $activeWeek = DB::table('picks_weeks')
                    ->where('season_id', $activeSeason->id)
                    ->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                          ->from('picks_events')
                          ->whereColumn('picks_events.week_id', 'picks_weeks.id')
                          ->whereIn('picks_events.status', ['scheduled', 'in_progress']);
                    })
                    ->orderByRaw("CASE season_type WHEN 'regular' THEN 0 ELSE 1 END")
                    ->orderBy('week_number', 'asc')
                    ->first();
            }

            if ($activeWeek) {
                // Season is active — no off-season context needed
                return new JsonResponse([
                    'is_active'          => true,
                    'is_off_season'      => false,
                    'retention_expired'  => false,
                    'days_since_ended'   => null,
                    'last_week_id'       => null,
                    'last_season_id'     => null,
                    'last_season_name'   => null,
                ]);
            }

            // ── No active week — find the most recently completed season ───────
            // The most recent season is the one with the highest year that has
            // at least one finished event.
            $lastSeason = DB::table('picks_seasons')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('picks_weeks')
                      ->join('picks_events', 'picks_events.week_id', '=', 'picks_weeks.id')
                      ->whereColumn('picks_weeks.season_id', 'picks_seasons.id')
                      ->where('picks_events.status', 'finished');
                })
                ->orderByDesc('year')
                ->first();

            if (! $lastSeason) {
                // No completed seasons at all — truly no data
                return new JsonResponse([
                    'is_active'          => false,
                    'is_off_season'      => false,
                    'retention_expired'  => false,
                    'days_since_ended'   => null,
                    'last_week_id'       => null,
                    'last_season_id'     => null,
                    'last_season_name'   => null,
                ]);
            }

            // ── When did this season end? ─────────────────────────────────────
            // Use the MAX(updated_at) of finished events in the last season —
            // this is set by ScorePicksJob when the final game is scored.
            $lastFinishedAt = DB::table('picks_events')
                ->join('picks_weeks', 'picks_events.week_id', '=', 'picks_weeks.id')
                ->where('picks_weeks.season_id', $lastSeason->id)
                ->where('picks_events.status', 'finished')
                ->max('picks_events.updated_at');

            $daysSinceEnded = $lastFinishedAt
                ? (int) Carbon::parse($lastFinishedAt)->diffInDays(Carbon::now())
                : null;

            $retentionExpired = $daysSinceEnded !== null && $daysSinceEnded > self::RETENTION_DAYS;

            // ── Find the last week of that season ─────────────────────────────
            // Use the week with the highest week_number that has finished events.
            // Postseason takes precedence over regular season in display.
            $lastWeek = DB::table('picks_weeks')
                ->where('season_id', $lastSeason->id)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('picks_events')
                      ->whereColumn('picks_events.week_id', 'picks_weeks.id')
                      ->where('picks_events.status', 'finished');
                })
                ->orderByRaw("CASE season_type WHEN 'postseason' THEN 0 ELSE 1 END")
                ->orderBy('week_number', 'desc')
                ->first();

            return new JsonResponse([
                'is_active'          => false,
                'is_off_season'      => true,
                'retention_expired'  => $retentionExpired,
                'days_since_ended'   => $daysSinceEnded,
                'last_week_id'       => $lastWeek?->id ?? null,
                'last_season_id'     => (int) $lastSeason->id,
                'last_season_name'   => $lastSeason->name,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'is_active'          => false,
                'is_off_season'      => false,
                'retention_expired'  => false,
                'days_since_ended'   => null,
                'last_week_id'       => null,
                'last_season_id'     => null,
                'last_season_name'   => null,
            ]);
        }
    }
}
