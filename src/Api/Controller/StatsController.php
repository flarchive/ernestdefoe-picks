<?php

namespace Resofire\Picks\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Resofire\Picks\Pick;
use Resofire\Picks\PickEvent;
use Resofire\Picks\Team;
use Resofire\Picks\Week;

class StatsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertCan('picks.manage');

        $params = $request->getQueryParams();
        $weekId = Arr::get($params, 'week_id');

        // ── Participation ────────────────────────────────────────────────────
        $totalPlayers = Pick::distinct('user_id')->count('user_id');

        $totalGamesThisWeek  = $weekId
            ? PickEvent::where('week_id', (int) $weekId)->count()
            : 0;

        $picksThisWeek = $weekId
            ? Pick::whereHas('event', fn ($q) => $q->where('week_id', (int) $weekId))->count()
            : 0;

        $uniquePickersThisWeek = $weekId
            ? Pick::whereHas('event', fn ($q) => $q->where('week_id', (int) $weekId))
                ->distinct('user_id')->count('user_id')
            : 0;

        $participationRate = ($totalPlayers > 0 && $weekId)
            ? round($uniquePickersThisWeek / $totalPlayers * 100, 1)
            : null;

        $usersNotPickedThisWeek = $weekId
            ? max(0, $totalPlayers - $uniquePickersThisWeek)
            : null;

        // ── Accuracy & Scoring ───────────────────────────────────────────────
        $scoredPicks = Pick::whereNotNull('is_correct');

        $avgAccuracyAllTime = null;
        if ($scoredPicks->count() > 0) {
            $correct = (clone $scoredPicks)->where('is_correct', true)->count();
            $total   = $scoredPicks->count();
            $avgAccuracyAllTime = round($correct / $total * 100, 1);
        }

        $avgAccuracyThisWeek = null;
        if ($weekId) {
            $weekScored = Pick::whereNotNull('is_correct')
                ->whereHas('event', fn ($q) => $q->where('week_id', (int) $weekId));
            $weekTotal   = $weekScored->count();
            $weekCorrect = (clone $weekScored)->where('is_correct', true)->count();
            if ($weekTotal > 0) {
                $avgAccuracyThisWeek = round($weekCorrect / $weekTotal * 100, 1);
            }
        }

        // Most picked team — the team with the most picks across all events
        $mostPickedTeam = null;
        $homePickCounts = Pick::join('picks_events', 'picks_picks.event_id', '=', 'picks_events.id')
            ->where('picks_picks.selected_outcome', 'home')
            ->groupBy('picks_events.home_team_id')
            ->selectRaw('picks_events.home_team_id as team_id, COUNT(*) as cnt')
            ->orderByDesc('cnt')
            ->first();

        $awayPickCounts = Pick::join('picks_events', 'picks_picks.event_id', '=', 'picks_events.id')
            ->where('picks_picks.selected_outcome', 'away')
            ->groupBy('picks_events.away_team_id')
            ->selectRaw('picks_events.away_team_id as team_id, COUNT(*) as cnt')
            ->orderByDesc('cnt')
            ->first();

        $topTeamId  = null;
        $topTeamCnt = 0;

        if ($homePickCounts && $homePickCounts->cnt > $topTeamCnt) {
            $topTeamId  = $homePickCounts->team_id;
            $topTeamCnt = $homePickCounts->cnt;
        }
        if ($awayPickCounts && $awayPickCounts->cnt > $topTeamCnt) {
            $topTeamId  = $awayPickCounts->team_id;
            $topTeamCnt = $awayPickCounts->cnt;
        }

        if ($topTeamId) {
            $team = Team::find($topTeamId);
            $mostPickedTeam = $team
                ? ['name' => $team->name, 'abbreviation' => $team->abbreviation, 'picks' => $topTeamCnt]
                : null;
        }

        // Upset rate — % of finished games where majority picked the loser
        $upsets         = 0;
        $finishedWithPicks = 0;

        $finishedEvents = PickEvent::where('status', PickEvent::STATUS_FINISHED)
            ->whereNotNull('result')
            ->get();

        foreach ($finishedEvents as $event) {
            $homePicks = Pick::where('event_id', $event->id)->where('selected_outcome', 'home')->count();
            $awayPicks = Pick::where('event_id', $event->id)->where('selected_outcome', 'away')->count();
            $total     = $homePicks + $awayPicks;

            if ($total === 0) continue;

            $finishedWithPicks++;
            $majorityPicked = $homePicks >= $awayPicks ? 'home' : 'away';

            if ($majorityPicked !== $event->result) {
                $upsets++;
            }
        }

        $upsetRate = $finishedWithPicks > 0
            ? round($upsets / $finishedWithPicks * 100, 1)
            : null;

        // ── Game Coverage ────────────────────────────────────────────────────
        $totalFinished    = PickEvent::where('status', PickEvent::STATUS_FINISHED)->count();
        $totalScheduled   = PickEvent::where('status', PickEvent::STATUS_SCHEDULED)->count();
        $gamesNoPicks     = PickEvent::whereDoesntHave('picks')->count();

        // Consensus games — all picks went the same way
        $consensusCount = 0;
        $contestedGames = [];

        $eventsWithPicks = PickEvent::has('picks')->get();
        foreach ($eventsWithPicks as $event) {
            $home  = Pick::where('event_id', $event->id)->where('selected_outcome', 'home')->count();
            $away  = Pick::where('event_id', $event->id)->where('selected_outcome', 'away')->count();
            $total = $home + $away;

            if ($total === 0) continue;

            $homePct = $home / $total;

            if ($homePct === 1.0 || $homePct === 0.0) {
                $consensusCount++;
            }

            // Most contested = closest to 50/50
            $split = abs($homePct - 0.5);
            $homeTeam = Team::find($event->home_team_id);
            $awayTeam = Team::find($event->away_team_id);

            $contestedGames[] = [
                'event_id'  => $event->id,
                'home_team' => $homeTeam?->abbreviation ?? '?',
                'away_team' => $awayTeam?->abbreviation ?? '?',
                'home_pct'  => round($homePct * 100, 1),
                'away_pct'  => round((1 - $homePct) * 100, 1),
                'total'     => $total,
                'split'     => $split,
            ];
        }

        usort($contestedGames, fn ($a, $b) => $a['split'] <=> $b['split']);
        $mostContested = array_slice($contestedGames, 0, 3);

        // Remove internal split field before sending
        $mostContested = array_map(function ($g) {
            unset($g['split']);
            return $g;
        }, $mostContested);

        return new JsonResponse([
            'participation' => [
                'total_players'              => $totalPlayers,
                'unique_pickers_this_week'   => $uniquePickersThisWeek,
                'picks_this_week'            => $picksThisWeek,
                'total_games_this_week'      => $totalGamesThisWeek,
                'participation_rate'         => $participationRate,
                'users_not_picked_this_week' => $usersNotPickedThisWeek,
            ],
            'accuracy' => [
                'avg_accuracy_all_time'  => $avgAccuracyAllTime,
                'avg_accuracy_this_week' => $avgAccuracyThisWeek,
                'upset_rate'             => $upsetRate,
                'most_picked_team'       => $mostPickedTeam,
            ],
            'coverage' => [
                'total_finished'   => $totalFinished,
                'total_scheduled'  => $totalScheduled,
                'games_no_picks'   => $gamesNoPicks,
                'consensus_games'  => $consensusCount,
                'most_contested'   => $mostContested,
            ],
        ]);
    }
}
