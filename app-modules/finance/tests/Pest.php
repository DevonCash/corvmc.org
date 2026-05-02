<?php

pest()->extend(Tests\TestCase::class)
    ->in('Feature', 'Feature/Workflows', 'Feature/Listeners', 'Unit');
