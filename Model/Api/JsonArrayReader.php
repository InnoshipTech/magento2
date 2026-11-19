<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model\Api;

use UnexpectedValueException;

/**
 * Reads a file holding one JSON array and yields its object/array elements one at a time, so a list of
 * several hundred thousand entries never sits decoded in memory at once.
 *
 * Throws when the file is not a JSON array or ends before the array is closed (a truncated download):
 * callers that delete what the list no longer contains must not do so on a partial list.
 * Scalar elements of the array are skipped.
 */
class JsonArrayReader
{
    private const CHUNK_SIZE = 65536;

    /**
     * @param string $path
     *
     * @return \Generator<int, array>
     * @throws UnexpectedValueException
     * @throws \JsonException
     */
    public function read(string $path): \Generator
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new UnexpectedValueException('Cannot open ' . $path);
        }

        try {
            $depth = 0;
            $opened = false;
            $closed = false;
            $inString = false;
            $collecting = false;
            $element = '';
            // bytes of the next chunk still covered by an escape that started at the end of the last one
            $carry = 0;

            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new UnexpectedValueException('Cannot read ' . $path);
                }
                $length = strlen($chunk);
                $pos = $carry;
                $carry = 0;
                $start = 0;

                while ($pos < $length) {
                    if ($inString) {
                        $pos += strcspn($chunk, '"\\', $pos);
                        if ($pos >= $length) {
                            break;
                        }
                        if ($chunk[$pos] === '\\') {
                            $pos += 2;
                            continue;
                        }
                        $inString = false;
                        $pos++;
                        continue;
                    }

                    $pos += strcspn($chunk, '"{}[]', $pos);
                    if ($pos >= $length) {
                        break;
                    }
                    $char = $chunk[$pos];

                    if ($char === '"') {
                        if ($depth === 0) {
                            throw new UnexpectedValueException('The answer is not a JSON array');
                        }
                        $inString = true;
                    } elseif ($char === '{' || $char === '[') {
                        if ($depth === 0) {
                            if ($opened || $char === '{') {
                                throw new UnexpectedValueException('The answer is not a JSON array');
                            }
                            $opened = true;
                        } elseif ($depth === 1) {
                            $collecting = true;
                            $element = '';
                            $start = $pos;
                        }
                        $depth++;
                    } else {
                        $depth--;
                        if ($depth < 0) {
                            throw new UnexpectedValueException('Unbalanced JSON in ' . $path);
                        }
                        if ($depth === 1 && $collecting) {
                            $element .= substr($chunk, $start, $pos - $start + 1);
                            $collecting = false;
                            $decoded = json_decode($element, true, 512, JSON_THROW_ON_ERROR);
                            $element = '';
                            yield $decoded;
                        } elseif ($depth === 0) {
                            $closed = true;
                        }
                    }
                    $pos++;
                }

                if ($pos > $length) {
                    $carry = $pos - $length;
                }
                if ($collecting) {
                    $element .= substr($chunk, $start);
                }
            }

            if (!$opened || !$closed || $depth !== 0) {
                throw new UnexpectedValueException('The JSON array in ' . $path . ' is incomplete');
            }
        } finally {
            fclose($handle);
        }
    }
}
