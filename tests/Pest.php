<?php

use Tests\TestCase;

/*
| Feature tests boot the Laravel app (container, facades, config).
| Unit tests stay plain PHP.
*/
pest()->extend(TestCase::class)->in('Feature');
