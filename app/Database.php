<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(): PDO
    {
        // Fallback values are for local Docker development only.
        // In production all DB_* env vars must be set explicitly — no defaults.
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'postindex';
        $user = getenv('DB_USER') ?: 'postindex';
        $pass = getenv('DB_PASS') ?: 'secret';

        return new PDO(
          "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
          $user,
          $pass,
          [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          ]
        );
    }
}