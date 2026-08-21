---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Queue retry_after must exceed the longest job timeout
GenerateVideo has $timeout = 600. The database queue connection's retry_after is raised to 630 in config/queue.php for this reason — if retry_after were below the job timeout, the worker would re-dispatch a render while it is still running. If you add a longer-running job, raise retry_after with it. Also: frames keep sequence gaps when deleted (never resequence rows — it races with concurrent captures); GenerateVideo copies frames into gapless frame_%06d.jpg temp names before running ffmpeg, so gaps are harmless.
