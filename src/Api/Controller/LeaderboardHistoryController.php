<?php

namespace Resofire\Picks\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Database\Capsule\Manager as DB;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Resofire\Picks\UserScore;

/**
 * GET /picks/leaderboard-history
 *
 * Returns the final standings for every completed past season.
 * The current (in-progress) season is excluded — it belongs on the
 * Season tab of the live leaderboard, not the history stack.
 *
 * Uses Eloquent with('user') so display_name and avatar_url go through
 * Flarum's model accessors correctly, matching ListLeaderboardController.
 *
 * Permission: picks.view — same as the live leaderboard.
 */
class LeaderboardHistoryController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('picks.view');

        try {
            // ── Identify the current season (has unfinished games) ───────────
            // Scoped to the most recent season with unfinished games so that
            // future unplayed weeks in other seasons don't interfere.
            $currentSeasonRow = DB::table('picks_seasons')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('picks_weeks')
                      ->join('picks_events', 'picks_events.week_id', '=', 'picks_weeks.id')
                      ->whereColumn('picks_weeks.season_id', 'picks_seasons.id')
                      ->whereIn('picks_events.status', ['scheduled', 'in_progress']);
                })
                ->orderByDesc('year')
                ->first();

            $currentSeasonId = $currentSeasonRow?->id ?? null;

            // ── Load all seasons except the current one, newest first ─────────
            $seasonsQuery = DB::table('picks_seasons')->orderByDesc('year');

            if ($currentSeasonId) {
                $seasonsQuery->where('id', '!=', $currentSeasonId);
            }

            $seasons = $seasonsQuery->get();

            if ($seasons->isEmpty()) {
                return new JsonResponse(['seasons' => []]);
            }

            $seasonsData = [];

            foreach ($seasons as $season) {
                // Load season-level scores via Eloquent so user accessors work
                $scores = UserScore::with('user')
                    ->where('season_id', $season->id)
                    ->whereNull('week_id')
                    ->where('total_picks', '>', 0)
                    ->orderByDesc('total_points')
                    ->orderByDesc('correct_picks')
                    ->get();

                $entries = [];
                $rank    = 1;

                foreach ($scores as $score) {
                    $entries[] = [
                        'rank'          => $rank,
                        'user_id'       => (int) $score->user_id,
                        'username'      => $score->user?->username,
                        'display_name'  => $score->user?->display_name ?? $score->user?->username,
                        'avatar_url'    => $score->user?->avatarUrl,
                        'total_picks'   => (int) $score->total_picks,
                        'correct_picks' => (int) $score->correct_picks,
                        'total_points'  => (int) $score->total_points,
                        'accuracy'      => (float) $score->accuracy,
                    ];
                    $rank++;
                }

                $seasonsData[] = [
                    'season_id' => (int) $season->id,
                    'name'      => $season->name,
                    'year'      => (int) $season->year,
                    'standings' => $entries,
                ];
            }

            return new JsonResponse(['seasons' => $seasonsData]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to load leaderboard history.'], 500);
        }
    }
}
