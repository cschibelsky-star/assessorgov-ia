<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The application uses Spatie activity logging, but this repository does
        // not yet contain the package migration. Keep characterization tests
        // isolated from audit persistence until that schema decision is made.
        activity()->disableLogging();
    }
}
