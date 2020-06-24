<?php

// If a .env file exists put each non-empty line into the environment
if (file_exists(__DIR__ . '/.env')) {
    $env = new SplFileObject(__DIR__ . '/.env');
    while (!$env->eof()) {
        if (($line = trim($env->fgets()))) {
            putenv($line);
        }
    }
}