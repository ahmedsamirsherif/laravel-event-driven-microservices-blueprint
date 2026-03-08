<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeProjectionCollection extends ResourceCollection
{
    public $collects = EmployeeProjectionResource::class;

    public array $extraMeta = [];

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => array_merge(
                [
                    'current_page' => $this->currentPage(),
                    'last_page'    => $this->lastPage(),
                    'per_page'     => $this->perPage(),
                    'total'        => $this->total(),
                ],
                $this->extraMeta,
            ),
        ];
    }

    /**
     * Suppress the default pagination information so it doesn't
     * merge duplicate keys into the response via array_merge_recursive.
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }
}
