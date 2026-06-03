<?php

namespace Resofire\Picks\Api\Resource;

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Resofire\Picks\Week;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<Week>
 */
class WeekResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'picks-weeks';
    }

    public function model(): string
    {
        return Week::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query->orderByRaw("CASE season_type WHEN 'regular' THEN 0 ELSE 1 END")
              ->orderBy('week_number', 'asc');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->authenticated(),
            Endpoint\Show::make()->authenticated(),
            Endpoint\Update::make()->authenticated()->can('picks.manage'),
            Endpoint\Delete::make()->authenticated()->can('picks.manage'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->writable()
                ->maxLength(100),

            Schema\Integer::make('seasonId')
                ->get(fn (Week $w) => $w->season_id),

            Schema\Integer::make('weekNumber')
                ->nullable()
                ->get(fn (Week $w) => $w->week_number),

            Schema\Str::make('seasonType')
                ->get(fn (Week $w) => $w->season_type),

            Schema\Str::make('startDate')
                ->nullable()
                ->get(fn (Week $w) => $w->start_date),

            Schema\Str::make('endDate')
                ->nullable()
                ->get(fn (Week $w) => $w->end_date),

            Schema\Boolean::make('isOpen')
                ->get(fn (Week $w) => (bool) $w->is_open),

            Schema\Relationship\ToOne::make('season')
                ->includable()
                ->type('picks-seasons'),
        ];
    }
}
