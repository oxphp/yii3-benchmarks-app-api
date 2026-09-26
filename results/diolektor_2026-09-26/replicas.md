# Replicated results, 2026-09-26

The runs in this directory and `report.html` are one complete suite pass. The same suite was run eight times in total
to check how far a single pass can be trusted; this page summarizes all eight.

## How the replicas were made

- Four UpCloud HICPU-16xCPU-32GB machines (16 vCPU, AMD EPYC 9575F), each placed on a different physical host.
  Machines of one account that share a host slow each other down by 25–45% under this load while reported steal stays
  at 2–4%, so separate hosts were enforced rather than assumed.
- Before the suite, each machine ran a short gate benchmark and was replaced if it scored below 90% of a reference.
- Each machine ran the full suite twice, once in the default runtime order and once reversed, so every runtime was
  measured 8 times on each endpoint. Load generator, database and runtime shared the machine, as in a normal run.
- After each pass the first runtime was measured again. Its result moved by −3.4% to +4.6%, so conditions did not
  drift within a pass.
- The pass published here (second machine, first pass) is the one closest to the median of all eight: its results
  differ from the per-runtime medians by 1.7% on average.

## Reading the numbers

Absolute RPS differs between hosts by up to about 10% for every runtime at once, so compare runtimes by their ratio to
PHP-FPM + Nginx measured on the same machine in the same pass. Those ratios and the ranking are much more stable than
the absolute numbers. The order on `/` was the same in all eight passes, apart from runtimes within 3% of each other.
On `/postgres/orders` OxPHP worker and FrankenPHP worker are a tie: OxPHP worker was ahead in 3 of 8 passes.

Column notes:

- **Max RPS** is the best successful RPS of any stage.
- **App CPU µs/response** is the CPU time of the runtime's containers (without PostgreSQL and Valkey) on the saturated
  plateau, divided by the successful RPS.
- **Highest stage with p99 < 10 ms and no errors** is the highest stage that reached 95% of its target with zero errors
  and p99 latency under 10 ms; 0 means not even the first 2,500 RPS stage met that bar.
- **Errors** are summed over all eight replicas and all stages.

Per-replica values for every runtime are in `replicas.json`.

## `/`

| Runtime | Max RPS, median | Min–max | Relative to PHP-FPM, median (min–max) | App CPU µs/response, median | Highest stage with p99 < 10 ms and no errors, median | Errors, all replicas | Replicas |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Rapira dispatcher | 90,565 | 84,317–95,776 | 12.49 (12.33–12.86) | 125 | 2,500 | 119,478 | 8 |
| Rapira worker | 87,564 | 79,058–95,701 | 12.03 (11.67–13.04) | 130 | 2,500 | 116,876 | 8 |
| OxPHP worker | 73,143 | 67,930–80,876 | 10.24 (9.28–11.21) | 164 | 50,000 | 0 | 8 |
| FrankenPHP worker | 45,356 | 42,440–48,300 | 6.27 (6.00–6.47) | 211 | 35,000 | 0 | 8 |
| RoadRunner | 30,308 | 28,417–33,039 | 4.23 (4.03–4.41) | 387 | 25,000 | 0 | 8 |
| FreeUnit | 8,206 | 7,600–8,481 | 1.12 (1.10–1.18) | 1744 | 5,000 | 0 | 8 |
| Rapira classic | 7,790 | 7,341–8,049 | 1.07 (1.05–1.10) | 1813 | 1,250 | 2,010 | 8 |
| PHP-FPM + Nginx | 7,268 | 6,748–7,556 | 1.00 (1.00–1.00) | 1912 | 2,500 | 0 | 8 |
| FrankenPHP classic | 6,989 | 6,475–7,515 | 0.96 (0.94–1.00) | 2001 | 2,500 | 0 | 8 |
| OxPHP classic | 5,916 | 5,524–6,188 | 0.82 (0.75–0.86) | 2455 | 0 | 0 | 8 |

## `/postgres/orders`

| Runtime | Max RPS, median | Min–max | Relative to PHP-FPM, median (min–max) | App CPU µs/response, median | Highest stage with p99 < 10 ms and no errors, median | Errors, all replicas | Replicas |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Rapira dispatcher | 14,156 | 13,128–14,846 | 3.12 (3.03–3.20) | 362 | 0 | 5,610 | 8 |
| Rapira worker | 14,074 | 12,933–14,419 | 3.10 (2.94–3.26) | 376 | 0 | 5,070 | 8 |
| FrankenPHP worker | 12,415 | 11,251–13,371 | 2.72 (2.67–2.85) | 449 | 5,000 | 0 | 8 |
| OxPHP worker | 12,289 | 11,392–12,857 | 2.70 (2.64–2.87) | 508 | 10,000 | 0 | 8 |
| RoadRunner | 8,898 | 8,350–9,559 | 1.98 (1.91–2.06) | 823 | 5,000 | 0 | 8 |
| FrankenPHP classic | 4,683 | 4,303–5,017 | 1.04 (1.00–1.08) | 2405 | 0 | 0 | 8 |
| FreeUnit | 4,570 | 4,190–4,981 | 1.03 (0.94–1.06) | 2226 | 0 | 0 | 8 |
| PHP-FPM + Nginx | 4,512 | 4,149–4,749 | 1.00 (1.00–1.00) | 2395 | 0 | 0 | 8 |
| Rapira classic | 4,390 | 3,986–4,472 | 0.95 (0.94–1.00) | 2279 | 0 | 1,106 | 8 |
| OxPHP classic | 3,364 | 3,196–3,566 | 0.77 (0.70–0.78) | 3236 | 0 | 0 | 8 |

