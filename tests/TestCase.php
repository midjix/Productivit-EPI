<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les tests ne dépendent pas des assets compilés par Vite (absents en CI) :
        // @vite() et Vite::fonts() rendent des balises vides.
        $this->withoutVite();
    }
}
