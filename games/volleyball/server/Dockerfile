FROM ubuntu:24.04

ARG GODOT_VERSION=4.7.1
ARG GODOT_SHA256=c7ff14fd28472c8d4f193043de30278dcf7e5241a1dcf7566b02e27addaa33ba

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl libfontconfig1 libxkbcommon0 unzip; \
    rm -rf /var/lib/apt/lists/*; \
    useradd --create-home --uid 10001 godot

RUN set -eux; \
    curl --fail --location --show-error --silent \
        --retry 8 --retry-delay 5 --retry-all-errors --continue-at - \
        "https://github.com/godotengine/godot-builds/releases/download/${GODOT_VERSION}-stable/Godot_v${GODOT_VERSION}-stable_linux.x86_64.zip" \
        -o /tmp/godot.zip; \
    echo "${GODOT_SHA256}  /tmp/godot.zip" | sha256sum -c -; \
    unzip /tmp/godot.zip -d /tmp/godot; \
    install -m 0755 "/tmp/godot/Godot_v${GODOT_VERSION}-stable_linux.x86_64" /usr/local/bin/godot; \
    rm -rf /tmp/godot /tmp/godot.zip

WORKDIR /app
COPY --chown=godot:godot project/ /app/

USER godot
EXPOSE 9001
STOPSIGNAL SIGINT

CMD ["godot", "--headless", "--path", "/app", "--", "--server", "--port=9001"]
