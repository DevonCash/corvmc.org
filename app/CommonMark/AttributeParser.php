<?php

namespace App\CommonMark;

class AttributeParser
{
    /**
     * Parse a `{key=value key2="quoted value" .class #id}` attribute string.
     *
     * @return array<string, string>
     */
    public static function parse(string $input): array
    {
        $attrs = [];
        $input = trim($input);

        if ($input === '') {
            return $attrs;
        }

        $length = strlen($input);
        $pos = 0;

        while ($pos < $length) {
            // Skip whitespace
            while ($pos < $length && $input[$pos] === ' ') {
                $pos++;
            }

            if ($pos >= $length) {
                break;
            }

            // Shorthand .class
            if ($input[$pos] === '.') {
                $pos++;
                $value = self::readWord($input, $pos);
                $attrs['class'] = isset($attrs['class']) ? $attrs['class'] . ' ' . $value : $value;

                continue;
            }

            // Shorthand #id
            if ($input[$pos] === '#') {
                $pos++;
                $attrs['id'] = self::readWord($input, $pos);

                continue;
            }

            // key=value pair
            $key = self::readWord($input, $pos);
            if ($key === '') {
                $pos++;

                continue;
            }

            if ($pos < $length && $input[$pos] === '=') {
                $pos++; // skip =

                if ($pos < $length && $input[$pos] === '"') {
                    $pos++; // skip opening quote
                    $value = '';
                    while ($pos < $length && $input[$pos] !== '"') {
                        if ($input[$pos] === '\\' && $pos + 1 < $length) {
                            $pos++;
                        }
                        $value .= $input[$pos];
                        $pos++;
                    }
                    if ($pos < $length) {
                        $pos++; // skip closing quote
                    }
                    $attrs[$key] = $value;
                } else {
                    $attrs[$key] = self::readWord($input, $pos);
                }
            } else {
                // Boolean attribute
                $attrs[$key] = $key;
            }
        }

        return $attrs;
    }

    private static function readWord(string $input, int &$pos): string
    {
        $start = $pos;
        $length = strlen($input);

        while ($pos < $length && $input[$pos] !== ' ' && $input[$pos] !== '=' && $input[$pos] !== '"' && $input[$pos] !== '}') {
            $pos++;
        }

        return substr($input, $start, $pos - $start);
    }
}
