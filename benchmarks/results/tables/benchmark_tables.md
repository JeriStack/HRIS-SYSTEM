# Phase 15 — DSA Benchmark Performance Tables

## 1. Search Algorithms Performance Summary

| Dataset Size | Algorithm | Search Scenario | Median Time (µs) | Comparisons / Probes | Theoretical Complexity |
|---:|---|---|---:|---:|---:|
| 10 (DS-010) | LinearSearch | beginning | 1.20 µs | 1 comps | O(1) |
| 10 (DS-010) | BinarySearch | beginning | 5.00 µs | 5 comps | O(log N) |
| 10 (DS-010) | HashTableLookup | beginning | 0.90 µs | 1 probes | O(1) avg |
| 10 (DS-010) | LinearSearch | middle | 4.90 µs | 6 comps | O(N) |
| 10 (DS-010) | BinarySearch | middle | 5.00 µs | 5 comps | O(log N) |
| 10 (DS-010) | HashTableLookup | middle | 0.90 µs | 1 probes | O(1) avg |
| 10 (DS-010) | LinearSearch | end | 7.80 µs | 10 comps | O(N) |
| 10 (DS-010) | BinarySearch | end | 6.60 µs | 7 comps | O(log N) |
| 10 (DS-010) | HashTableLookup | end | 0.90 µs | 1 probes | O(1) avg |
| 10 (DS-010) | LinearSearch | miss | 7.70 µs | 10 comps | O(N) |
| 10 (DS-010) | BinarySearch | miss | 7.60 µs | 8 comps | O(log N) |
| 10 (DS-010) | HashTableLookup | miss | 1.40 µs | 2 probes | O(1) avg |
| 100 (DS-100) | LinearSearch | beginning | 3.00 µs | 1 comps | O(1) |
| 100 (DS-100) | BinarySearch | beginning | 11.80 µs | 11 comps | O(log N) |
| 100 (DS-100) | HashTableLookup | beginning | 0.90 µs | 1 probes | O(1) avg |
| 100 (DS-100) | LinearSearch | middle | 39.90 µs | 51 comps | O(N) |
| 100 (DS-100) | BinarySearch | middle | 11.80 µs | 11 comps | O(log N) |
| 100 (DS-100) | HashTableLookup | middle | 1.00 µs | 2 probes | O(1) avg |
| 100 (DS-100) | LinearSearch | end | 74.70 µs | 100 comps | O(N) |
| 100 (DS-100) | BinarySearch | end | 13.40 µs | 13 comps | O(log N) |
| 100 (DS-100) | HashTableLookup | end | 0.90 µs | 1 probes | O(1) avg |
| 100 (DS-100) | LinearSearch | miss | 73.40 µs | 100 comps | O(N) |
| 100 (DS-100) | BinarySearch | miss | 14.30 µs | 14 comps | O(log N) |
| 100 (DS-100) | HashTableLookup | miss | 1.50 µs | 1 probes | O(1) avg |
| 500 (DS-500) | LinearSearch | beginning | 10.50 µs | 1 comps | O(1) |
| 500 (DS-500) | BinarySearch | beginning | 21.70 µs | 15 comps | O(log N) |
| 500 (DS-500) | HashTableLookup | beginning | 1.00 µs | 3 probes | O(1) avg |
| 500 (DS-500) | LinearSearch | middle | 190.55 µs | 251 comps | O(N) |
| 500 (DS-500) | BinarySearch | middle | 21.90 µs | 15 comps | O(log N) |
| 500 (DS-500) | HashTableLookup | middle | 0.90 µs | 1 probes | O(1) avg |
| 500 (DS-500) | LinearSearch | end | 368.70 µs | 500 comps | O(N) |
| 500 (DS-500) | BinarySearch | end | 23.50 µs | 17 comps | O(log N) |
| 500 (DS-500) | HashTableLookup | end | 0.90 µs | 1 probes | O(1) avg |
| 500 (DS-500) | LinearSearch | miss | 381.35 µs | 500 comps | O(N) |
| 500 (DS-500) | BinarySearch | miss | 24.50 µs | 18 comps | O(log N) |
| 500 (DS-500) | HashTableLookup | miss | 1.40 µs | 1 probes | O(1) avg |
| 1000 (DS-1000) | LinearSearch | beginning | 20.30 µs | 1 comps | O(1) |
| 1000 (DS-1000) | BinarySearch | beginning | 31.90 µs | 17 comps | O(log N) |
| 1000 (DS-1000) | HashTableLookup | beginning | 0.90 µs | 1 probes | O(1) avg |
| 1000 (DS-1000) | LinearSearch | middle | 390.65 µs | 501 comps | O(N) |
| 1000 (DS-1000) | BinarySearch | middle | 31.90 µs | 17 comps | O(log N) |
| 1000 (DS-1000) | HashTableLookup | middle | 0.90 µs | 1 probes | O(1) avg |
| 1000 (DS-1000) | LinearSearch | end | 747.75 µs | 1000 comps | O(N) |
| 1000 (DS-1000) | BinarySearch | end | 33.40 µs | 19 comps | O(log N) |
| 1000 (DS-1000) | HashTableLookup | end | 0.90 µs | 1 probes | O(1) avg |
| 1000 (DS-1000) | LinearSearch | miss | 822.65 µs | 1000 comps | O(N) |
| 1000 (DS-1000) | BinarySearch | miss | 35.10 µs | 20 comps | O(log N) |
| 1000 (DS-1000) | HashTableLookup | miss | 1.50 µs | 1 probes | O(1) avg |
| 5000 (DS-5000) | LinearSearch | beginning | 169.30 µs | 1 comps | O(1) |
| 5000 (DS-5000) | BinarySearch | beginning | 107.95 µs | 23 comps | O(log N) |
| 5000 (DS-5000) | HashTableLookup | beginning | 0.90 µs | 1 probes | O(1) avg |
| 5000 (DS-5000) | LinearSearch | middle | 4267.55 µs | 2501 comps | O(N) |
| 5000 (DS-5000) | BinarySearch | middle | 107.15 µs | 23 comps | O(log N) |
| 5000 (DS-5000) | HashTableLookup | middle | 0.90 µs | 2 probes | O(1) avg |
| 5000 (DS-5000) | LinearSearch | end | 6671.25 µs | 5000 comps | O(N) |
| 5000 (DS-5000) | BinarySearch | end | 107.70 µs | 25 comps | O(log N) |
| 5000 (DS-5000) | HashTableLookup | end | 0.90 µs | 1 probes | O(1) avg |
| 5000 (DS-5000) | LinearSearch | miss | 6891.65 µs | 5000 comps | O(N) |
| 5000 (DS-5000) | BinarySearch | miss | 110.20 µs | 26 comps | O(log N) |
| 5000 (DS-5000) | HashTableLookup | miss | 1.40 µs | 1 probes | O(1) avg |
| 10000 (DS-10000) | LinearSearch | beginning | 191.80 µs | 1 comps | O(1) |
| 10000 (DS-10000) | BinarySearch | beginning | 196.85 µs | 25 comps | O(log N) |
| 10000 (DS-10000) | HashTableLookup | beginning | 0.90 µs | 1 probes | O(1) avg |
| 10000 (DS-10000) | LinearSearch | middle | 5590.20 µs | 5001 comps | O(N) |
| 10000 (DS-10000) | BinarySearch | middle | 191.70 µs | 25 comps | O(log N) |
| 10000 (DS-10000) | HashTableLookup | middle | 0.90 µs | 1 probes | O(1) avg |
| 10000 (DS-10000) | LinearSearch | end | 10816.55 µs | 10000 comps | O(N) |
| 10000 (DS-10000) | BinarySearch | end | 200.05 µs | 27 comps | O(log N) |
| 10000 (DS-10000) | HashTableLookup | end | 0.90 µs | 1 probes | O(1) avg |
| 10000 (DS-10000) | LinearSearch | miss | 10934.85 µs | 10000 comps | O(N) |
| 10000 (DS-10000) | BinarySearch | miss | 196.05 µs | 28 comps | O(log N) |
| 10000 (DS-10000) | HashTableLookup | miss | 1.40 µs | 1 probes | O(1) avg |

## 2. Sorting Algorithms Execution Time on Random Data

| Dataset Size | BubbleSort (ms) | SelectionSort (ms) | InsertionSort (ms) | MergeSort (ms) | QuickSort (ms) |
|---:|---:|---:|---:|---:|---:|
| 10 | 0.055 | 0.051 | 0.027 | 0.032 | 0.044 |
| 100 | 5.710 | 5.473 | 2.805 | 0.700 | 0.872 |
| 500 | 148.825 | 141.335 | 73.287 | 4.875 | 6.649 |
| 1000 | 671.630 | 603.457 | 389.653 | 11.217 | 14.034 |
| 5000 | 20302.283 | 19965.334 | 9619.654 | 94.331 | 90.637 |
| 10000 | 95576.673 | 83104.121 | 37288.677 | 168.388 | 199.854 |

## 3. Impact of Input Arrangement on Sorting Performance (N=1,000)

| Algorithm | Random (ms) | Already Sorted (ms) | Reverse Sorted (ms) | Nearly Sorted (ms) |
|---|---:|---:|---:|---:|
| BubbleSort | 671.630 | 1.306 | 701.950 | 5.962 |
| SelectionSort | 603.457 | 613.885 | 621.166 | 585.680 |
| InsertionSort | 389.653 | 1.410 | 610.627 | 1.432 |
| MergeSort | 11.217 | 7.178 | 7.684 | 7.253 |
| QuickSort | 14.034 | 12.174 | 20.043 | 11.886 |

## 4. Operation Counts Comparison (N=1,000 Random Records)

| Algorithm | Comparisons | Swaps | Moves / Assignments |
|---|---:|---:|---:|
| BubbleSort | 497,289 | 250,260 | 0 |
| SelectionSort | 499,500 | 996 | 0 |
| InsertionSort | 251,252 | 0 | 251,259 |
| MergeSort | 8,700 | 0 | 19,952 |
| QuickSort | 10,580 | 5,344 | 0 |
