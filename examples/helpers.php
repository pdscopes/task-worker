<?php

if (!function_exists('value')) {
    /**
     * Return the value of the given $value. If $value is a closure evaluate.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    function value($value) {
        return $value instanceof Closure ? $value() : $value;
    }
}
if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    function env($key, $default = null) {
        $value = getenv($key);

        if ($value === false) {
            return value($default);
        }

        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
            case 'empty':
                return '';
        }

        if (strlen($value) > 1 && strpos($value, '"') === 1 && strpos($value, '"') === strlen($value)) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}