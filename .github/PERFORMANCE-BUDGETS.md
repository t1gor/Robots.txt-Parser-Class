# Performance budgets

`performance.yml` fails a job when a parse runs past its `budget` (seconds). This records where
those numbers come from, so tightening them later is a measurement rather than a guess.

## How they were set

Three Performance runs per side, same code within a side, `parse_seconds` from each job's
`benchmark.json` artifact. Budget is ~1.6-2x the slowest of the three.

| case | master | this code | worst | budget |
| --- | --- | --- | --- | --- |
| 250 MB | 3.40 / 3.44 / 3.53 | 2.71 / 3.37 / 3.41 | 3.41 | 6 |
| 600 MB | 3.16 / 5.98 / 8.05 | 6.13 / 8.17 / 8.25 | 8.25 | 14 |
| 1 GB | 6.02 / 6.24 / 13.82 | 13.82 / 13.92 / 14.09 | 14.09 | 24 |
| 250 MB rules only | 24.05 / 28.42 / 28.49 | 12.60 / 13.24 / 20.50 | 20.50 | 32 |
| Few agents, 50k rules each | 3.01 / 3.08 / 3.08 | 1.38 / 2.47 / 2.50 | 2.50 | 5 |

## Why the margin is not padding

The margin absorbs the runner, not the parser. Every matrix entry is a separate job on a separate
machine, and the hosted fleet is not uniform: unchanged master parsed 600 MB at 190 MB/s on one
run and 75 MB/s on another, and 1 GB took 6.02 s and 13.82 s on runs of the same commit.
`cpu_percent` is 100 in every job and `cpu_seconds` tracks wall clock, so this is the CPU the job
was given, not disk contention - nothing about how the benchmark is measured can remove it.

So a budget set at the observed best would fail on unchanged code. These budgets catch a collapse
or a regression of roughly 1.7x and worse. They do **not** catch drift of tens of percent.

## What these budgets cannot do

Locking in a specific win needs a machine-independent number, which seconds are not. Concretely:
`Few agents, 50k rules each` runs at 1.38-2.50 s here against 3.01-3.08 s on master - a genuine
~2x win - but the two ranges are close enough that no single threshold separates them on every
runner. The budget therefore sits at 5 s, which master would also pass.

To gate that properly the benchmark would have to normalise against the machine it landed on, for
example by timing a small fixed reference parse in the same job and gating on the ratio of the two.
That would need validating across several runs before it could be trusted, since it assumes the
reference and the real workload scale together across machine classes.

## Re-measuring

```sh
gh workflow run performance.yml --ref <branch>          # repeat 3x
gh run download <run-id> -D <dir>                       # benchmark.json per case
```

Take the slowest of three per case, apply the same margin, and update both the table above and the
`budget` values in `performance.yml`.
