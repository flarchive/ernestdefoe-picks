<?php

namespace Resofire\Picks\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Resofire\Picks\Team;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<Team>
 */
class TeamResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'picks-teams';
    }

    public function model(): string
    {
        return Team::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        // Teams are readable by anyone with picks.view.
        // No row-level visibility scoping needed beyond endpoint authorization.
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated(),
            Endpoint\Show::make()
                ->authenticated(),
            Endpoint\Create::make()
                ->authenticated()
                ->can('picks.manage'),
            Endpoint\Update::make()
                ->authenticated()
                ->can('picks.manage'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('picks.manage'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(100),

            Schema\Str::make('slug')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(100),

            Schema\Str::make('abbreviation')
                ->nullable()
                ->writable()
                ->maxLength(10),

            Schema\Str::make('conference')
                ->nullable()
                ->writable()
                ->maxLength(100),

            Schema\Integer::make('cfbdId')
                ->nullable()
                ->writable()
                ->get(fn (Team $team) => $team->cfbd_id)
                ->set(fn (Team $team, $value) => $team->cfbd_id = $value),

            Schema\Integer::make('espnId')
                ->nullable()
                ->writable()
                ->get(fn (Team $team) => $team->espn_id)
                ->set(fn (Team $team, $value) => $team->espn_id = $value),

            Schema\Str::make('logoPath')
                ->nullable()
                ->writable()
                ->get(fn (Team $team) => $team->logo_path)
                ->set(fn (Team $team, $value) => $team->logo_path = $value),

            Schema\Str::make('logoDarkPath')
                ->nullable()
                ->writable()
                ->get(fn (Team $team) => $team->logo_dark_path)
                ->set(fn (Team $team, $value) => $team->logo_dark_path = $value),

            Schema\Boolean::make('logoCustom')
                ->writable()
                ->get(fn (Team $team) => $team->logo_custom)
                ->set(fn (Team $team, $value) => $team->logo_custom = $value),

            // Computed URL attributes — read-only, derived from logo_path + base URL.
            Schema\Str::make('logoUrl')
                ->nullable()
                ->get(fn (Team $team) => $team->logo_url),

            Schema\Str::make('logoDarkUrl')
                ->nullable()
                ->get(fn (Team $team) => $team->logo_dark_url),
        ];
    }
}
