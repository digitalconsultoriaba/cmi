<?php

namespace Tests;

use Database\Seeders\StructuralSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Com RefreshDatabase, roda os seeders estruturais (lookups + roles) por teste. */
    protected $seed = true;

    protected $seeder = StructuralSeeder::class;
}
