<?php

namespace Resofire\Picks\Api\Resource;

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Resofire\Picks\Season;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<Season>
 */
class SeasonResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'picks-seasons';
    }

    public function model(): string
    {
        return Season::class;
    }

    public function scope(Builder $query, Context $context): void
    {
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

            Schema\Str::make('slug')
                ->writable()
                ->maxLength(100),

            Schema\Integer::make('year')
                ->get(fn (Season $s) => $s->year),

            Schema\Str::make('startDate')
                ->nullable()
                ->get(fn (Season $s) => $s->start_date),

            Schema\Str::make('endDate')
                ->nullable()
                ->get(fn (Season $s) => $s->end_date),
        ];
    }
}
