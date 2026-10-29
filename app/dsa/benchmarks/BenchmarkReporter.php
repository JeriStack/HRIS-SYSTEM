<?php
/**
 * Benchmark Reporter and Exporter
 * Generates JSON, CSV, Markdown tables, and SVG vector charts from measured benchmark runs
 * Employee HRIS & Leave Management System — Phase 15
 */

namespace App\Dsa\Benchmarks;

class BenchmarkReporter
{
    /**
     * Export raw results as pretty-printed JSON.
     *
     * @param list<BenchmarkResult> $results
     */
    public static function exportJson(array $results, string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = array_map(fn(BenchmarkResult $r) => $r->toArray(), $results);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Export raw results as CSV.
     *
     * @param list<BenchmarkResult> $results
     */
    public static function exportCsv(array $results, string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($path, 'w');
        if (!$fp) return;

        // CSV Header
        fputcsv($fp, [
            'run_id', 'timestamp', 'category', 'algorithm', 'dataset_id', 'dataset_size',
            'arrangement', 'scenario', 'repetitions', 'min_time_ns', 'median_time_ns',
            'mean_time_ns', 'max_time_ns', 'std_dev_ns', 'median_time_ms', 'median_time_us',
            'comparisons', 'swaps', 'moves', 'probes', 'memory_peak_bytes', 'is_correct'
        ], ',', '"', "\\");

        foreach ($results as $r) {
            fputcsv($fp, [
                $r->runId, $r->timestamp, $r->category, $r->algorithm, $r->datasetId,
                $r->datasetSize, $r->arrangement, $r->scenario, $r->repetitions,
                $r->minTimeNs, $r->medianTimeNs, $r->meanTimeNs, $r->maxTimeNs, $r->stdDevNs,
                $r->getMedianTimeMs(), $r->getMedianTimeUs(), $r->comparisons, $r->swaps,
                $r->moves, $r->probes, $r->memoryPeakBytes, $r->isCorrect ? 1 : 0
            ], ',', '"', "\\");
        }

        fclose($fp);
    }

    /**
     * Generate Markdown summary tables.
     *
     * @param list<BenchmarkResult> $results
     * @return string
     */
    public static function generateMarkdownTables(array $results): string
    {
        $md = "# Phase 15 — DSA Benchmark Performance Tables\n\n";

        // 1. Search Comparison Table
        $md .= "## 1. Search Algorithms Performance Summary\n\n";
        $md .= "| Dataset Size | Algorithm | Search Scenario | Median Time (µs) | Comparisons / Probes | Theoretical Complexity |\n";
        $md .= "|---:|---|---|---:|---:|---:|\n";

        foreach ($results as $r) {
            if ($r->category !== 'search') continue;

            $complexity = match ($r->algorithm) {
                'LinearSearch'    => ($r->scenario === 'beginning') ? 'O(1)' : 'O(N)',
                'BinarySearch'    => 'O(log N)',
                'HashTableLookup' => 'O(1) avg',
                default           => 'O(N)',
            };

            $ops = ($r->algorithm === 'HashTableLookup') ? "{$r->probes} probes" : "{$r->comparisons} comps";
            $md .= sprintf(
                "| %d (%s) | %s | %s | %0.2f µs | %s | %s |\n",
                $r->datasetSize, $r->datasetId, $r->algorithm, $r->scenario,
                $r->getMedianTimeUs(), $ops, $complexity
            );
        }

        // 2. Sorting by Dataset Size on Random Data
        $md .= "\n## 2. Sorting Algorithms Execution Time on Random Data\n\n";
        $md .= "| Dataset Size | BubbleSort (ms) | SelectionSort (ms) | InsertionSort (ms) | MergeSort (ms) | QuickSort (ms) |\n";
        $md .= "|---:|---:|---:|---:|---:|---:|\n";

        $sizes = [10, 100, 500, 1000, 5000, 10000];
        foreach ($sizes as $size) {
            $row = ["size" => $size];
            foreach (['BubbleSort', 'SelectionSort', 'InsertionSort', 'MergeSort', 'QuickSort'] as $algo) {
                $match = array_filter($results, fn($r) => $r->category === 'sort' && $r->algorithm === $algo && $r->datasetSize === $size && $r->arrangement === 'random');
                $first = reset($match);
                $row[$algo] = $first ? sprintf("%0.3f", $first->getMedianTimeMs()) : 'N/A';
            }
            $md .= sprintf(
                "| %d | %s | %s | %s | %s | %s |\n",
                $size, $row['BubbleSort'], $row['SelectionSort'], $row['InsertionSort'], $row['MergeSort'], $row['QuickSort']
            );
        }

        // 3. Sorting by Input Arrangement (at N=1000)
        $md .= "\n## 3. Impact of Input Arrangement on Sorting Performance (N=1,000)\n\n";
        $md .= "| Algorithm | Random (ms) | Already Sorted (ms) | Reverse Sorted (ms) | Nearly Sorted (ms) |\n";
        $md .= "|---|---:|---:|---:|---:|\n";

        foreach (['BubbleSort', 'SelectionSort', 'InsertionSort', 'MergeSort', 'QuickSort'] as $algo) {
            $row = ['algo' => $algo];
            foreach (['random', 'sorted', 'reverse_sorted', 'nearly_sorted'] as $arr) {
                $match = array_filter($results, fn($r) => $r->category === 'sort' && $r->algorithm === $algo && $r->datasetSize === 1000 && $r->arrangement === $arr);
                $first = reset($match);
                $row[$arr] = $first ? sprintf("%0.3f", $first->getMedianTimeMs()) : 'N/A';
            }
            $md .= sprintf(
                "| %s | %s | %s | %s | %s |\n",
                $algo, $row['random'], $row['sorted'], $row['reverse_sorted'], $row['nearly_sorted']
            );
        }

        // 4. Operation Counts (at N=1,000)
        $md .= "\n## 4. Operation Counts Comparison (N=1,000 Random Records)\n\n";
        $md .= "| Algorithm | Comparisons | Swaps | Moves / Assignments |\n";
        $md .= "|---|---:|---:|---:|\n";

        foreach (['BubbleSort', 'SelectionSort', 'InsertionSort', 'MergeSort', 'QuickSort'] as $algo) {
            $match = array_filter($results, fn($r) => $r->category === 'sort' && $r->algorithm === $algo && $r->datasetSize === 1000 && $r->arrangement === 'random');
            $first = reset($match);
            if ($first) {
                $md .= sprintf(
                    "| %s | %s | %s | %s |\n",
                    $algo, number_format($first->comparisons), number_format($first->swaps), number_format($first->moves)
                );
            }
        }

        return $md;
    }

    /**
     * Generate standalone SVG charts.
     *
     * @param list<BenchmarkResult> $results
     * @return array<string, string> Paths of generated SVGs
     */
    public static function generateSvgCharts(array $results, string $outputDir): array
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $generated = [];

        // Chart 1: Search Execution Time (Log scale simulation)
        $chart1Path = "{$outputDir}/chart_search_execution_time.svg";
        self::generateSearchChart($results, $chart1Path);
        $generated['search_time'] = $chart1Path;

        // Chart 2: Sorting Execution Time by N
        $chart2Path = "{$outputDir}/chart_sorting_execution_time.svg";
        self::generateSortingChart($results, $chart2Path);
        $generated['sort_time'] = $chart2Path;

        // Chart 3: Input Arrangements
        $chart3Path = "{$outputDir}/chart_sorting_arrangements.svg";
        self::generateArrangementsChart($results, $chart3Path);
        $generated['arrangements'] = $chart3Path;

        return $generated;
    }

    private static function generateSearchChart(array $results, string $path): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450" width="100%" height="100%">';
        $svg .= '<rect width="100%" height="100%" fill="#FFFFFF"/>';
        $svg .= '<text x="400" y="30" font-family="sans-serif" font-size="18" font-weight="bold" text-anchor="middle" fill="#0F172A">Search Execution Time vs Dataset Size (Median µs)</text>';
        $svg .= '<text x="400" y="50" font-family="sans-serif" font-size="12" fill="#64748B" text-anchor="middle">Comparing Linear Search (Worst/Miss) vs Binary Search vs Hash Table Lookup</text>';

        // Axes & Grid
        $svg .= '<line x1="80" y1="380" x2="740" y2="380" stroke="#CBD5E1" stroke-width="2"/>';
        $svg .= '<line x1="80" y1="70" x2="80" y2="380" stroke="#CBD5E1" stroke-width="2"/>';

        // X labels
        $sizes = [10, 100, 500, 1000, 5000, 10000];
        $xCoords = [140, 260, 380, 500, 620, 720];

        foreach ($sizes as $idx => $size) {
            $x = $xCoords[$idx];
            $svg .= "<line x1='{$x}' y1='380' x2='{$x}' y2='385' stroke='#64748B' stroke-width='1.5'/>";
            $svg .= "<text x='{$x}' y='402' font-family='sans-serif' font-size='11' text-anchor='middle' fill='#475569'>N={$size}</text>";
        }

        // Legend
        $svg .= '<rect x="180" y="70" width="14" height="14" fill="#EF4444"/>';
        $svg .= '<text x="200" y="82" font-family="sans-serif" font-size="12" fill="#334155">Linear Search (Miss)</text>';
        $svg .= '<rect x="360" y="70" width="14" height="14" fill="#3B82F6"/>';
        $svg .= '<text x="380" y="82" font-family="sans-serif" font-size="12" fill="#334155">Binary Search</text>';
        $svg .= '<rect x="510" y="70" width="14" height="14" fill="#10B981"/>';
        $svg .= '<text x="530" y="82" font-family="sans-serif" font-size="12" fill="#334155">Hash Table Lookup</text>';

        // Render data points for Linear Search (Miss), Binary Search (End), Hash Table (Miss)
        $linearPoints = [];
        $binaryPoints = [];
        $hashPoints   = [];

        // Max time cap for Y axis scaling (e.g. 500 µs)
        $maxTimeUs = 0.0;
        foreach ($results as $r) {
            if ($r->category === 'search' && $r->getMedianTimeUs() > $maxTimeUs) {
                $maxTimeUs = $r->getMedianTimeUs();
            }
        }
        $maxTimeUs = max(50.0, $maxTimeUs);

        foreach ($sizes as $idx => $size) {
            $x = $xCoords[$idx];

            // Linear search miss
            $lMatch = array_filter($results, fn($r) => $r->algorithm === 'LinearSearch' && $r->datasetSize === $size && $r->scenario === 'miss');
            $lVal = reset($lMatch) ? reset($lMatch)->getMedianTimeUs() : 0.0;
            $ly = 380 - min(300, (int)(($lVal / $maxTimeUs) * 280));
            $linearPoints[] = "{$x},{$ly}";
            $svg .= "<circle cx='{$x}' cy='{$ly}' r='4' fill='#EF4444'/>";

            // Binary search end
            $bMatch = array_filter($results, fn($r) => $r->algorithm === 'BinarySearch' && $r->datasetSize === $size && $r->scenario === 'end');
            $bVal = reset($bMatch) ? reset($bMatch)->getMedianTimeUs() : 0.0;
            $by = 380 - min(300, (int)(($bVal / $maxTimeUs) * 280));
            $binaryPoints[] = "{$x},{$by}";
            $svg .= "<circle cx='{$x}' cy='{$by}' r='4' fill='#3B82F6'/>";

            // Hash table lookup
            $hMatch = array_filter($results, fn($r) => $r->algorithm === 'HashTableLookup' && $r->datasetSize === $size && $r->scenario === 'end');
            $hVal = reset($hMatch) ? reset($hMatch)->getMedianTimeUs() : 0.0;
            $hy = 380 - min(300, (int)(($hVal / $maxTimeUs) * 280));
            $hashPoints[] = "{$x},{$hy}";
            $svg .= "<circle cx='{$x}' cy='{$hy}' r='4' fill='#10B981'/>";
        }

        $svg .= "<polyline points='" . implode(' ', $linearPoints) . "' fill='none' stroke='#EF4444' stroke-width='2.5'/>";
        $svg .= "<polyline points='" . implode(' ', $binaryPoints) . "' fill='none' stroke='#3B82F6' stroke-width='2.5'/>";
        $svg .= "<polyline points='" . implode(' ', $hashPoints) . "' fill='none' stroke='#10B981' stroke-width='2.5'/>";

        $svg .= '</svg>';
        file_put_contents($path, $svg);
    }

    private static function generateSortingChart(array $results, string $path): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450" width="100%" height="100%">';
        $svg .= '<rect width="100%" height="100%" fill="#FFFFFF"/>';
        $svg .= '<text x="400" y="30" font-family="sans-serif" font-size="18" font-weight="bold" text-anchor="middle" fill="#0F172A">Sorting Algorithms Execution Time vs Dataset Size</text>';
        $svg .= '<text x="400" y="50" font-family="sans-serif" font-size="12" fill="#64748B" text-anchor="middle">Execution Time (Milliseconds) on Random Data</text>';

        // Axes
        $svg .= '<line x1="80" y1="380" x2="740" y2="380" stroke="#CBD5E1" stroke-width="2"/>';
        $svg .= '<line x1="80" y1="70" x2="80" y2="380" stroke="#CBD5E1" stroke-width="2"/>';

        $sizes = [10, 100, 500, 1000, 5000, 10000];
        $xCoords = [140, 260, 380, 500, 620, 720];

        foreach ($sizes as $idx => $size) {
            $x = $xCoords[$idx];
            $svg .= "<line x1='{$x}' y1='380' x2='{$x}' y2='385' stroke='#64748B' stroke-width='1.5'/>";
            $svg .= "<text x='{$x}' y='402' font-family='sans-serif' font-size='11' text-anchor='middle' fill='#475569'>N={$size}</text>";
        }

        // Legend
        $algos = [
            'BubbleSort'    => '#EF4444',
            'SelectionSort' => '#F59E0B',
            'InsertionSort' => '#8B5CF6',
            'MergeSort'     => '#3B82F6',
            'QuickSort'     => '#10B981',
        ];

        $lx = 100;
        foreach ($algos as $algo => $color) {
            $svg .= "<rect x='{$lx}' y='70' width='12' height='12' fill='{$color}'/>";
            $svg .= "<text x='" . ($lx + 18) . "' y='81' font-family='sans-serif' font-size='11' fill='#334155'>{$algo}</text>";
            $lx += 130;
        }

        // Find max sorting time for scaling
        $maxTimeMs = 0.0;
        foreach ($results as $r) {
            if ($r->category === 'sort' && $r->arrangement === 'random' && $r->getMedianTimeMs() > $maxTimeMs) {
                $maxTimeMs = $r->getMedianTimeMs();
            }
        }
        $maxTimeMs = max(10.0, $maxTimeMs);

        foreach ($algos as $algo => $color) {
            $points = [];
            foreach ($sizes as $idx => $size) {
                $x = $xCoords[$idx];
                $match = array_filter($results, fn($r) => $r->algorithm === $algo && $r->datasetSize === $size && $r->arrangement === 'random');
                $val = reset($match) ? reset($match)->getMedianTimeMs() : 0.0;
                $y = 380 - min(290, (int)(($val / $maxTimeMs) * 270));
                $points[] = "{$x},{$y}";
                $svg .= "<circle cx='{$x}' cy='{$y}' r='4' fill='{$color}'/>";
            }
            $svg .= "<polyline points='" . implode(' ', $points) . "' fill='none' stroke='{$color}' stroke-width='2'/>";
        }

        $svg .= '</svg>';
        file_put_contents($path, $svg);
    }

    private static function generateArrangementsChart(array $results, string $path): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450" width="100%" height="100%">';
        $svg .= '<rect width="100%" height="100%" fill="#FFFFFF"/>';
        $svg .= '<text x="400" y="30" font-family="sans-serif" font-size="18" font-weight="bold" text-anchor="middle" fill="#0F172A">Sorting Input Arrangements Comparison (N=1,000)</text>';
        $svg .= '<text x="400" y="50" font-family="sans-serif" font-size="12" fill="#64748B" text-anchor="middle">Random vs Already Sorted vs Reverse Sorted vs Nearly Sorted</text>';

        // Axes
        $svg .= '<line x1="80" y1="380" x2="740" y2="380" stroke="#CBD5E1" stroke-width="2"/>';
        $svg .= '<line x1="80" y1="70" x2="80" y2="380" stroke="#CBD5E1" stroke-width="2"/>';

        $algos = ['BubbleSort', 'SelectionSort', 'InsertionSort', 'MergeSort', 'QuickSort'];
        $xCoords = [140, 270, 400, 530, 660];

        foreach ($algos as $idx => $algo) {
            $x = $xCoords[$idx];
            $svg .= "<line x1='{$x}' y1='380' x2='{$x}' y2='385' stroke='#64748B' stroke-width='1.5'/>";
            $svg .= "<text x='{$x}' y='402' font-family='sans-serif' font-size='11' text-anchor='middle' fill='#475569'>{$algo}</text>";
        }

        // Legend
        $arrangements = [
            'random'         => '#64748B',
            'sorted'         => '#10B981',
            'reverse_sorted' => '#EF4444',
            'nearly_sorted'  => '#3B82F6',
        ];

        $lx = 140;
        foreach ($arrangements as $arr => $color) {
            $svg .= "<rect x='{$lx}' y='70' width='12' height='12' fill='{$color}'/>";
            $svg .= "<text x='" . ($lx + 18) . "' y='81' font-family='sans-serif' font-size='11' fill='#334155'>{$arr}</text>";
            $lx += 140;
        }

        // Grouped bar chart per algorithm
        $maxTime = 0.0;
        foreach ($results as $r) {
            if ($r->category === 'sort' && $r->datasetSize === 1000 && $r->getMedianTimeMs() > $maxTime) {
                $maxTime = $r->getMedianTimeMs();
            }
        }
        $maxTime = max(5.0, $maxTime);

        $barWidth = 18;
        foreach ($algos as $idx => $algo) {
            $baseX = $xCoords[$idx] - 38;
            $offset = 0;
            foreach ($arrangements as $arr => $color) {
                $match = array_filter($results, fn($r) => $r->algorithm === $algo && $r->datasetSize === 1000 && $r->arrangement === $arr);
                $val = reset($match) ? reset($match)->getMedianTimeMs() : 0.0;
                $height = min(280, (int)(($val / $maxTime) * 260));
                $by = 380 - $height;
                $bx = $baseX + ($offset * ($barWidth + 2));

                $svg .= "<rect x='{$bx}' y='{$by}' width='{$barWidth}' height='{$height}' fill='{$color}' rx='2'/>";
                $offset++;
            }
        }

        $svg .= '</svg>';
        file_put_contents($path, $svg);
    }
}

