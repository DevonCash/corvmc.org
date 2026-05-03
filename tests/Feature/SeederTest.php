<?php

use Database\Seeders\DatabaseSeeder;

it('runs all seeders without errors', function () {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseHas('users', []);
});
