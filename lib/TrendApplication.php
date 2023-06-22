<?php

namespace staabm\PHPStanBaselineAnalysis;

use \Iterator;
use function Safe\file_get_contents;
use function Safe\json_decode;

final class TrendApplication
{
    /**
     * @api
     */
    const EXIT_IMPROVED = 0;
    /**
     * @api
     */
    const EXIT_STEADY = 1;
    /**
     * @api
     */
    const EXIT_WORSE = 2;

    private const OUTPUT_FORMAT_JSON = 'json';

    /**
     * @param self::OUTPUT_FORMAT_* $outputFormat
     *
     * @return self::EXIT_*
     * @throws \Safe\Exceptions\JsonException
     *
     * @throws \Safe\Exceptions\FilesystemException
     */
    public function start(string $referenceFilePath, string $comparingFilePath, string $outputFormat): int
    {
        $exitCode = self::EXIT_IMPROVED;

        $reader = new AnalyzerResultReader();
        $reference = $reader->readFile($referenceFilePath);
        $comparing = $reader->readFile($comparingFilePath);

        if ($outputFormat === self::OUTPUT_FORMAT_JSON) {
            return $this->createOutputJson($reference, $comparing, $exitCode);
        }

        return $this->createOutputPlainText($reference, $comparing, $exitCode);
    }

    public function help(): void
    {
        printf('USAGE: phpstan-baseline-trend <reference-result.json> <comparing-result.json>');
    }


    /**
     * @param array<string, AnalyzerResult> $reference
     * @param array<string, AnalyzerResult> $comparing
     *
     * @param self::EXIT_* $exitCode
     */
    private function createOutputPlainText(array $reference, array $comparing, int $exitCode): int
    {
        foreach ($reference as $baselinePath => $result) {
            echo 'Analyzing Trend for ' . $baselinePath . "\n";

            if (isset($comparing[$baselinePath])) {
                $exitCode = $this->compare(ResultPrinter::KEY_OVERALL_ERRORS, $result->overallErrors, $comparing[$baselinePath]->overallErrors, $exitCode);
                echo "\n";

                $exitCode = $this->compare(ResultPrinter::KEY_CLASSES_COMPLEXITY, $result->classesComplexity, $comparing[$baselinePath]->classesComplexity, $exitCode);
                echo "\n";

                $exitCode = $this->compare(ResultPrinter::KEY_DEPRECATIONS, $result->deprecations, $comparing[$baselinePath]->deprecations, $exitCode);
                echo "\n";

                $exitCode = $this->compare(ResultPrinter::KEY_INVALID_PHPDOCS, $result->invalidPhpdocs, $comparing[$baselinePath]->invalidPhpdocs, $exitCode);
                echo "\n";

                $exitCode = $this->compare(ResultPrinter::KEY_UNKNOWN_TYPES, $result->unknownTypes, $comparing[$baselinePath]->unknownTypes, $exitCode);
                echo "\n";

                $exitCode = $this->compare(ResultPrinter::KEY_ANONYMOUS_VARIABLES, $result->anonymousVariables, $comparing[$baselinePath]->anonymousVariables, $exitCode);
                echo "\n";

                $exitCode = $this->compare(ResultPrinter::KEY_UNUSED_SYMBOLS, $result->unusedSymbols, $comparing[$baselinePath]->unusedSymbols, $exitCode);
                echo "\n";
            }
        }

        return $exitCode;
    }

    /**
     * @param array<string, AnalyzerResult> $reference
     * @param array<string, AnalyzerResult> $comparing
     * @param self::EXIT_* $exitCode
     *
     * @return self::EXIT_*
     */
    private function createOutputJson(array $reference, array $comparing, int $exitCode): int
    {
        foreach ($reference as $baselinePath => $result) {

            $outputResult = new OutputResultJson('Analyzing Trend for ' . $baselinePath);

            if (isset($comparing[$baselinePath])) {
                $exitCode = $this->compareForJson($outputResult, ResultPrinter::KEY_OVERALL_ERRORS, $result->overallErrors, $comparing[$baselinePath]->overallErrors, $exitCode);
                $exitCode = $this->compareForJson($outputResult, ResultPrinter::KEY_CLASSES_COMPLEXITY, $result->classesComplexity, $comparing[$baselinePath]->classesComplexity, $exitCode);
                $exitCode = $this->compareForJson($outputResult, ResultPrinter::KEY_DEPRECATIONS, $result->deprecations, $comparing[$baselinePath]->deprecations, $exitCode);
                $exitCode = $this->compareForJson($outputResult, ResultPrinter::KEY_INVALID_PHPDOCS, $result->invalidPhpdocs, $comparing[$baselinePath]->invalidPhpdocs, $exitCode);
                $exitCode = $this->compareForJson($outputResult, ResultPrinter::KEY_UNKNOWN_TYPES, $result->unknownTypes, $comparing[$baselinePath]->unknownTypes, $exitCode);
                $exitCode = $this->compareForJson($outputResult, ResultPrinter::KEY_ANONYMOUS_VARIABLES, $result->anonymousVariables, $comparing[$baselinePath]->anonymousVariables, $exitCode);
                $exitCode = $this->compareForJson($outputResult, ResultPrinter::KEY_UNUSED_SYMBOLS, $result->unusedSymbols, $comparing[$baselinePath]->unusedSymbols, $exitCode);
            }

            echo json_encode($outputResult);
        }

        return $exitCode;
    }

    /**
     * @param ResultPrinter::KEY_* $key
     * @param int $referenceValue
     * @param int $comparingValue
     * @param self::EXIT_* $exitCode
     *
     * @return self::EXIT_*
     */
    private function compare($key, $referenceValue, $comparingValue, $exitCode): int
    {
        if ($comparingValue > $referenceValue) {
            printf('  %s: %d -> %d => worse', $key, $referenceValue, $comparingValue);

            $exitCode = max($exitCode, self::EXIT_WORSE);
        } elseif ($comparingValue < $referenceValue) {
            printf('  %s: %d -> %d => improved', $key, $referenceValue, $comparingValue);

            $exitCode = max($exitCode, self::EXIT_IMPROVED);
        } else {
            printf('  %s: %d -> %d => good', $key, $referenceValue, $comparingValue);

            $exitCode = max($exitCode, self::EXIT_STEADY);
        }
        return $exitCode;
    }

    /**
     * @param ResultPrinter::KEY_* $key
     * @param int $referenceValue
     * @param int $comparingValue
     * @param self::EXIT_* $exitCode
     *
     * @return self::EXIT_*
     */
    private function compareForJson(OutputResultJson $result, $key, $referenceValue, $comparingValue, $exitCode): int
    {
        if ($comparingValue > $referenceValue) {
            $result->output[$key] = [
                'reference' => $referenceValue,
                'comparing' => $comparingValue,
                'trend' => 'worse',
            ];

            $exitCode = max($exitCode, self::EXIT_WORSE);
        } elseif ($comparingValue < $referenceValue) {
            $result->output[$key] = [
                'reference' => $referenceValue,
                'comparing' => $comparingValue,
                'trend' => 'improved',
            ];

            $exitCode = max($exitCode, self::EXIT_IMPROVED);
        } else {
            $result->output[$key] = [
                'reference' => $referenceValue,
                'comparing' => $comparingValue,
                'trend' => 'good',
            ];

            $exitCode = max($exitCode, self::EXIT_STEADY);
        }
        return $exitCode;
    }
}
