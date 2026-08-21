---
paths:
  - 'app/Events/**'
---

# Events

## Broadcast events are a typed contract with resources/js/types/echo.ts
All broadcast events use broadcastAs() short names (remote.command, frame.captured, video.status) and camelCase broadcastWith() payload keys. The frontend listens with a leading dot and types every payload in resources/js/types/echo.ts. If you change an event name or payload shape, update echo.ts in the same commit. Channels are PUBLIC, named project.{remote_token} — the unguessable 40-char token is the security boundary because the app has no auth guard. All events implement ShouldBroadcastNow (queue latency would break capture timing).
