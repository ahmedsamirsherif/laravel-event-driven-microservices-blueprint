<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeCollection extends ResourceCollection
{
    public $collects = EmployeeResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page'    => $this->lastPage(),
                'per_page'     => $this->perPage(),
                'total'        => $this->total(),
            ],
        ];
    }

    /**
     * Suppress the default pagination information so it doesn't
     * merge duplicate `meta` into the response via array_merge_recursive.
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }
}
