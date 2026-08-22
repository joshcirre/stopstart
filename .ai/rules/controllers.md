---
paths:
  - app/Http/Controllers/VideoController.php
---

# Controllers

## Videos render client-side first; ffmpeg queue is the fallback
The primary render path is in the browser: mediabunny + WebCodecs encodes the frames to MP4 (resources/js/lib/client-renderer.svelte.ts) and POSTs the finished file to projects.videos.upload. The server-side GenerateVideo/ffmpeg queue render only serves browsers without WebCodecs H.264 — do not remove it, and remember Cloud's Flex managed queues cap jobs at 90 seconds with fractional CPU, so the fallback needs the queue instance sized generously (currently Flex 2GiB). The renderer must fetch frames via the same-origin projects.frames.image route: the bucket's signed URLs have no CORS headers, which blocks fetch() but not img tags.
