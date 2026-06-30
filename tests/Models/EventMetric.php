<?php

declare(strict_types=1);

namespace Statisty\Tests\Models;

use Illuminate\Database\Eloquent\Model;

final class EventMetric extends Model
{
    protected $table = 'events_metrics';
    public $timestamps = false;
}
