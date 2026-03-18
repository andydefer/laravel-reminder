<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Fixtures;

use Andydefer\LaravelReminder\Traits\Remindable;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalid test remindable that doesn't implement ShouldRemind.
 *
 * This fixture is used to test error handling when a model uses
 * the Remindable trait but doesn't implement the required interface.
 *
 * @package Andydefer\LaravelReminder\Tests\Fixtures
 */
class InvalidTestRemindable extends Model
{
    use Remindable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'test_remindable_models';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
    ];
}
