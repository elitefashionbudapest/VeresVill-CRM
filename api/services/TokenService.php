<?php
/**
 * Token generáló szolgáltatás
 */

class TokenService
{
    /**
     * Kriptográfiailag biztonságos hex token generálása
     */
    public static function generate(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Árajánlat token generálása (64 hex karakter)
     */
    public static function generateQuoteToken(): string
    {
        return self::generate(32);
    }
}
