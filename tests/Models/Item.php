<?php

declare(strict_types=1);

namespace Statisty\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'items';
    protected $guarded = [];
}
