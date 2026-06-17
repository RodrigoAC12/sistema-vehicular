<?php
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $host = 'localhost';
            $database = 'db_sistema_vehicular';
            $user = 'root';
            $password = '';
            $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

            try {
                self::$connection = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                json_response(false, 'No se pudo conectar a la base de datos', null, 500);
            }
        }

        return self::$connection;
    }
}
