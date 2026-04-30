<?php
/**
 * Copyright © InnoShip. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace InnoShip\InnoShip\Helper;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Security helper class for common security functions
 */
class Security extends AbstractHelper
{
    /**
     * Add security headers to JSON response
     *
     * @param Json $resultJson
     * @return Json
     */
    public function addSecurityHeaders(Json $resultJson): Json
    {
        // Prevent MIME type sniffing
        $resultJson->setHeader('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking
        $resultJson->setHeader('X-Frame-Options', 'DENY');

        // Enable XSS protection
        $resultJson->setHeader('X-XSS-Protection', '1; mode=block');

        // Referrer policy
        $resultJson->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy for JSON responses
        $resultJson->setHeader(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'"
        );

        return $resultJson;
    }

    /**
     * Sanitize string for output
     *
     * @param string $value
     * @param int $maxLength
     * @return string
     */
    public function sanitizeString(string $value, int $maxLength = 255): string
    {
        // Remove tags
        $value = strip_tags($value);

        // Encode HTML entities
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Limit length
        if (strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return $value;
    }

    /**
     * Validate country code (ISO 3166-1 alpha-2)
     *
     * @param string $countryCode
     * @return bool
     */
    public function isValidCountryCode(string $countryCode): bool
    {
        return preg_match('/^[A-Z]{2}$/', $countryCode) === 1;
    }

    /**
     * Validate numeric ID
     *
     * @param mixed $id
     * @return bool
     */
    public function isValidNumericId($id): bool
    {
        return is_numeric($id) && $id > 0;
    }

    /**
     * Sanitize courier ID from API response
     *
     * @param mixed $courierId
     * @return int|null
     */
    public function sanitizeCourierId($courierId): ?int
    {
        if (!$this->isValidNumericId($courierId)) {
            return null;
        }

        $id = (int)$courierId;

        // Validate range (reasonable courier ID)
        if ($id < 1 || $id > 999999) {
            return null;
        }

        return $id;
    }

    /**
     * Sanitize price value
     *
     * @param mixed $price
     * @param float $minValue
     * @param float $maxValue
     * @return float|null
     */
    public function sanitizePrice($price, float $minValue = 0.0, float $maxValue = 999999.99): ?float
    {
        if (!is_numeric($price)) {
            return null;
        }

        $sanitizedPrice = (float)$price;

        if ($sanitizedPrice < $minValue || $sanitizedPrice > $maxValue) {
            return null;
        }

        return round($sanitizedPrice, 2);
    }

    /**
     * Sanitize coordinates (latitude/longitude)
     *
     * @param mixed $coordinate
     * @param float $min
     * @param float $max
     * @return float|null
     */
    public function sanitizeCoordinate($coordinate, float $min, float $max): ?float
    {
        if (!is_numeric($coordinate)) {
            return null;
        }

        $value = (float)$coordinate;

        if ($value < $min || $value > $max) {
            return null;
        }

        return $value;
    }

    /**
     * Validate and sanitize latitude
     *
     * @param mixed $latitude
     * @return float|null
     */
    public function sanitizeLatitude($latitude): ?float
    {
        return $this->sanitizeCoordinate($latitude, -90.0, 90.0);
    }

    /**
     * Validate and sanitize longitude
     *
     * @param mixed $longitude
     * @return float|null
     */
    public function sanitizeLongitude($longitude): ?float
    {
        return $this->sanitizeCoordinate($longitude, -180.0, 180.0);
    }

    /**
     * Validate email format
     *
     * @param string $email
     * @return bool
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if string contains only safe characters for SQL
     * (additional layer of defense)
     *
     * @param string $value
     * @return bool
     */
    public function containsOnlySafeCharacters(string $value): bool
    {
        // Check for null bytes, control characters, or SQL injection patterns
        return !preg_match('/[\x00-\x1F\x7F]|--|\/\*|\*\/|;|UNION|SELECT|INSERT|UPDATE|DELETE|DROP/i', $value);
    }

    /**
     * Sanitize postal code
     *
     * @param string $postalCode
     * @param int $maxLength
     * @return string
     */
    public function sanitizePostalCode(string $postalCode, int $maxLength = 20): string
    {
        // Allow only alphanumeric, spaces, and dashes
        $postalCode = preg_replace('/[^a-zA-Z0-9\s-]/', '', $postalCode);

        // Trim and limit length
        $postalCode = trim($postalCode);
        if (strlen($postalCode) > $maxLength) {
            $postalCode = substr($postalCode, 0, $maxLength);
        }

        return $postalCode;
    }

    /**
     * Mask sensitive data for logging
     *
     * @param string $value
     * @param int $visibleChars Number of characters to show at start
     * @return string
     */
    public function maskSensitiveData(string $value, int $visibleChars = 4): string
    {
        $length = strlen($value);

        if ($length <= $visibleChars) {
            return str_repeat('*', $length);
        }

        $masked = substr($value, 0, $visibleChars) . str_repeat('*', $length - $visibleChars);

        return $masked;
    }

    /**
     * Check if value is within array (type-safe)
     *
     * @param mixed $needle
     * @param array $haystack
     * @return bool
     */
    public function inArrayStrict($needle, array $haystack): bool
    {
        return in_array($needle, $haystack, true);
    }
}
