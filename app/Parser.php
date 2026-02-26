<?php

namespace App;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        \ini_set('memory_limit', '1536M');

        $fh = \fopen($inputPath, 'rb');
        \stream_set_read_buffer($fh, 0);

        $result = [];
        $prefixLength = 19; // strlen('https://stitcher.io')
        $timestampLength = 25; // strlen('2026-02-25T12:00:00+00:00')
        $commaOffsetFromNewline = $timestampLength + 1;
        $chunkSize = 1024 * 1024;
        $buffer = '';

        while (! \feof($fh)) {
            $chunk = \fread($fh, $chunkSize);

            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;
            $offset = 0;

            while (true) {
                $newlinePos = \strpos($buffer, "\n", $offset);

                if ($newlinePos === false) {
                    break;
                }

                $commaPos = $newlinePos - $commaOffsetFromNewline;
                $pathStart = $offset + $prefixLength;
                $path = \substr($buffer, $pathStart, $commaPos - $pathStart);
                $date = \substr($buffer, $commaPos + 1, 10);

                if (isset($result[$path][$date])) {
                    $result[$path][$date]++;
                } else {
                    $result[$path][$date] = 1;
                }

                $offset = $newlinePos + 1;
            }

            if ($offset > 0) {
                $buffer = \substr($buffer, $offset);
            }
        }

        if ($buffer !== '') {
            $commaPos = \strlen($buffer) - $timestampLength - 1;
            $path = \substr($buffer, $prefixLength, $commaPos - $prefixLength);
            $date = \substr($buffer, $commaPos + 1, 10);

            if (isset($result[$path][$date])) {
                $result[$path][$date]++;
            } else {
                $result[$path][$date] = 1;
            }
        }

        foreach ($result as &$dates) {
            \ksort($dates, \SORT_STRING);
        }
        unset($dates);

        \file_put_contents($outputPath, \json_encode($result, \JSON_PRETTY_PRINT));
    }
}
