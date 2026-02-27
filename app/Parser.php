<?php

namespace App;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        $fh = \fopen($inputPath, 'rb');
        \stream_set_read_buffer($fh, 0);

        $result = [];
        $prefixLength = 19; // strlen('https://stitcher.io')
        $timestampLength = 25; // strlen('2026-02-25T12:00:00+00:00')
        $commaOffsetFromNewline = $timestampLength + 1;
        $chunkSize = 1024 * 1024;
        $buffer = '';
        $parsedDateCache = [];

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
                $rawDate = \substr($buffer, $commaPos + 1, 10);

                if (isset($parsedDateCache[$rawDate])) {
                    $date = $parsedDateCache[$rawDate];
                } else {
                    $parsedDateCache[$rawDate] = $date = (int) (
                        \substr($buffer, $commaPos + 1, 4)
                        . \substr($buffer, $commaPos + 6, 2)
                        . \substr($buffer, $commaPos + 9, 2)
                    );
                }

                $pathDates = &$result[$path];

                if (isset($pathDates[$date])) {
                    $pathDates[$date]++;
                } else {
                    $pathDates[$date] = 1;
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
            $rawDate = \substr($buffer, $commaPos + 1, 10);

            if (isset($parsedDateCache[$rawDate])) {
                $date = $parsedDateCache[$rawDate];
            } else {
                $parsedDateCache[$rawDate] = $date = (int) (
                    \substr($buffer, $commaPos + 1, 4)
                    . \substr($buffer, $commaPos + 6, 2)
                    . \substr($buffer, $commaPos + 9, 2)
                );
            }

            $pathDates = &$result[$path];

            if (isset($pathDates[$date])) {
                $pathDates[$date]++;
            } else {
                $pathDates[$date] = 1;
            }
        }

        $formattedDatesByInt = \array_flip($parsedDateCache);

        foreach ($result as &$dates) {
            \ksort($dates, \SORT_NUMERIC);

            $formattedDates = [];

            foreach ($dates as $date => $count) {
                $formattedDates[$formattedDatesByInt[$date]] = $count;
            }

            $dates = $formattedDates;
        }
        unset($dates);

        \file_put_contents($outputPath, \json_encode($result, \JSON_PRETTY_PRINT));
    }
}
