<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Builds a SQL query by replacing placeholders with their corresponding values and handling conditional parts.
     *
     * @param string $query The SQL query with placeholders to replace.
     * @param array $args An array of values to use as replacements for the placeholders.
     * @return string The built SQL query with placeholders replaced by their corresponding values.
     * @throws Exception If there is a missing argument for a placeholder.
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $index = 0;

        // Handle query placeholders
        $query = $this->handleQueryPlaceholders($query, $args, $index);

        // Handle conditional parts of a query
        return $this->handleConditions($query, $args);
    }

    /**
     * Replaces query placeholders with their corresponding values.
     *
     * @param string $query The SQL query with placeholders to replace.
     * @param array $args An array of values to use as replacements for the placeholders.
     * @param int &$index A reference to the index used to keep track of the current argument being replaced.
     * @return string The modified query with placeholders replaced by their corresponding values.
     * @throws Exception If there is a missing argument for a placeholder.
     */
    private function handleQueryPlaceholders(string $query, array $args, &$index): string
    {
        return preg_replace_callback(
         '/\?([dfa#]?)/', function ($matches) use (&$index, $args) {
            $type = $matches[1];

            if (!isset($args[$index])) {
                throw new Exception('Missing argument for placeholder');
            }

            $value = $args[$index++];

            return $this->manageTypeReplacement($type, $value);
        }, $query);
    }

    /**
     * @param string $query The original query string
     * @param array $args The array of arguments used for condition replacements
     * @return string The updated query string with condition replacements
     */
    private function handleConditions(string $query, array $args): string
    {
        return preg_replace_callback('/{([^}]*)}/', function ($matches) use ($args) {
            $condition = $matches[1];

            if (str_contains($condition, '?') && in_array($this->skip(), $args)) {
                return '';
            }

            return $condition;
        }, $query);
    }

    /**
     * Manages the replacement of query placeholders with their corresponding values based on type.
     *
     * @param string $type The type of placeholder to replace.
     * @param mixed $value The value to use as replacement for the placeholder based on its type.
     * @return string The modified value based on its type.
     * @throws Exception If the type is invalid or if the value does not match the expected type.
     */
    private function manageTypeReplacement(string $type, mixed $value): string
    {
        return match ($type) {
            'd' => strval(intval($value)),
            'f' => strval(floatval($value)),
            'a' => $this->arrayTypeReplacement($value),
            '#' => $this->poundTypeReplacement($value),
            default => $this->defaultPlaceholderProcess($value),
        };
    }

    /**
     * Replaces the placeholder ?a with the corresponding escaped value.
     *
     * @param mixed $value The value to be used as replacement for the placeholder.
     * @return string The escaped value to be substituted for the placeholder.
     * @throws Exception If the value is not an array.
     */
    private function arrayTypeReplacement(mixed $value): string
    {
        if (!is_array($value)) {
            throw new Exception('Expected array for placeholder ?a');
        }
        return $this->escapeValue($value, true);
    }

    /**
     * Performs pound type replacement.
     *
     * @param mixed $value The value to be escaped and converted to a string.
     * @return string The escaped and converted string value.
     */
    private function poundTypeReplacement(mixed $value): string
    {
        return $this->escapeValue($value, is_array($value));
    }

    /**
     * Escapes a value using the real_escape_string method of the mysqli object.
     *
     * @param mixed $value The value to be escaped.
     * @param bool $isArray Indicates whether the value is an array or not.
     * @return string The escaped value as a string.
     */
    private function escapeValue(mixed $value, bool $isArray): string
    {
        if ($isArray) {
            return implode(', ', array_map([$this->mysqli, 'real_escape_string'], $value));
        }
        return $this->escape($value);
    }

    /**
     * Processes a default placeholder value.
     *
     * @param mixed $value The value to process.
     * @return string The processed value as a string.
     */
    private function defaultPlaceholderProcess(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return $this->escape($value);
    }

    /**
     * Escapes a value to prevent SQL injection attacks.
     *
     * @param mixed $value The value to be escaped.
     * @return string The escaped value.
     */
    private function escape(mixed $value): string
    {
        return $this->mysqli->real_escape_string($value);
    }

    /**
     * Skips a specified database query.
     *
     * @return string The skip identifier used in database queries.
     */
    public function skip(): string
    {
        return '__DB_SKIP__';
    }
}
