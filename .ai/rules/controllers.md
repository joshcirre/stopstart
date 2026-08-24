---
paths:
  - app/Http/Controllers/VideoController.php
  - 'app/Http/Controllers/{DubController,AudioLayerController}.php'
---

# Controllers

## Videos render client-side first; ffmpeg queue is the fallback
The primary render path is in the browser: mediabunny + WebCodecs encodes the frames to MP4 (resources/js/lib/client-renderer.svelte.ts) and POSTs the finished file to projects.videos.upload. The server-side GenerateVideo/ffmpeg queue render only serves browsers without WebCodecs H.264 — do not remove it, and remember Cloud's Flex managed queues cap jobs at 90 seconds with fractional CPU, so the fallback needs the queue instance sized generously (currently Flex 2GiB). The renderer must fetch frames via the same-origin projects.frames.image route: the bucket's signed URLs have no CORS headers, which blocks fetch() but not img tags.

## Dub media stays on same-origin token routes; has_audio=false defines the master
Anything the dub page's JavaScript fetch()es (layer audio, master video bytes) must stream through the same-origin remote.* routes via Storage::response — bucket signed URLs carry no CORS headers and only work for <video>/<img> src. All dub endpoints are keyed by remote_token (phones have no owner cookie). A Video with has_audio=false is a render "master" (drives Show/Index/Capture via latestMasterVideo and is the dub source); has_audio=true is an export (latestExport). Exports must never enter pending/processing — they are created completed from client uploads, which is what keeps them out of the render in-flight guards.
