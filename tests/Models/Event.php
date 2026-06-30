<?php

declare(strict_types=1);

namespace Statisty\Tests\Models;

use Illuminate\Database\Eloquent\Model;

final class Event extends Model
{
    protected $table = 'events';
    public $timestamps = false;
}
