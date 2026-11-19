<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;

final class Paginate
{
    /**
     * Run each item of a paginator through an API resource while keeping the
     * plain paginator shape ({data, links, current_page, total, ...}) that the
     * frontend's Paginated<T> type and <Pagination> component expect.
     *
     * @param  class-string<JsonResource>  $resource
     */
    public static function through(LengthAwarePaginator $paginator, string $resource): LengthAwarePaginator
    {
        return $paginator->through(fn ($model) => (new $resource($model))->resolve(request()));
    }
}
