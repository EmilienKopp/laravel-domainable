<?php

namespace Splitstack\Domainable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class ModelWithoutProvidesEntity extends Model
{
    protected $table = 'example_models';

    public function __construct(public string $name) {}
}
