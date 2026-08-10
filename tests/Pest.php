<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)

    ->in('Feature');
