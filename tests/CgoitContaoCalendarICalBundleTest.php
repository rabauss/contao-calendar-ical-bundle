<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarICalBundle;

use PHPUnit\Framework\TestCase;

class CgoitContaoCalendarICalBundleTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $bundle = new CgoitContaoCalendarICalBundle();

        $this->assertInstanceOf(CgoitContaoCalendarICalBundle::class, $bundle);
    }
}
