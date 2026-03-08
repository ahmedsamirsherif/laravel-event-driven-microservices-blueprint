<?php

declare(strict_types=1);

use App\Domain\Employee\Models\Media;

it('has correct model config and employees relationship', function () {
    $media = new Media();
    expect($media->getFillable())->toBe(['original_name', 'url', 'mime_type', 'size', 'disk'])
        ->and($media->getTable())->toBe('media');

    $relation = $media->employees();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class)
        ->and($relation->getTable())->toBe('employee_media');
});
