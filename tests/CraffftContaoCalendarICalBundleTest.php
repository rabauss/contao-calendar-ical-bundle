<?php

namespace Craffft\ContaoCalendarICalBundle;

use PHPUnit\Framework\TestCase;

class CraffftContaoCalendarICalBundleTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $bundle = new CraffftContaoCalendarICalBundle();

        $this->assertInstanceOf(CraffftContaoCalendarICalBundle::class, $bundle);
    }
}
