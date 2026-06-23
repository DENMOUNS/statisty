<?php

declare(strict_types=1);

namespace Statisty\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class ModelProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
