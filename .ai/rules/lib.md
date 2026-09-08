---
paths:
  - 'resources/js/lib/*.svelte.ts'
---

# Lib

## Pass render settings per call, never cache them on the instance
ClientRenderer takes fps as a `render(frames, fps)` argument, not a constructor field. The page builds the renderer once inside untrack(), so anything captured at construction freezes at page-load value: Inertia re-renders projects/Show in place after a PATCH, which updates props but never rebuilds the class. A cached fps silently encoded every re-render at the original rate while the DB row recorded the new one — the video and its label disagreed. Any future per-render setting (quality, dimensions on a resizable project) must be an argument too.
