#!/usr/bin/env sh
# Downloads a static linux-arm64 ffmpeg binary into bin/ during Laravel Cloud
# builds. Cloud compute (Debian 12, aarch64) ships without ffmpeg, and the
# GenerateVideo job needs it; FFMPEG_PATH points here in production.
set -e

if [ -x bin/ffmpeg ]; then
    echo "ffmpeg already present, skipping download"
    exit 0
fi

mkdir -p bin
curl -fsSL https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-arm64-static.tar.xz -o /tmp/ffmpeg-static.tar.xz
tar -xJf /tmp/ffmpeg-static.tar.xz -C bin --strip-components=1 --wildcards '*/ffmpeg'
rm -f /tmp/ffmpeg-static.tar.xz
chmod +x bin/ffmpeg

# Version check is informational only: the build host may not share the
# runtime's architecture, in which case executing the binary here fails.
bin/ffmpeg -version 2>/dev/null | head -1 || echo "downloaded bin/ffmpeg (not executable on this build host)"
