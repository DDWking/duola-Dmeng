FROM ubuntu:24.04

ARG GODOT_VERSION=4.7.1
ARG GODOT_SHA256=c7ff14fd28472c8d4f193043de30278dcf7e5241a1dcf7566b02e27addaa33ba

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates libfontconfig1 libxkbcommon0 unzip; \
    rm -rf /var/lib/apt/lists/*; \
    useradd --create-home --uid 10001 godot

COPY vendor/Godot_v4.7.1-stable_linux.x86_64.zip /tmp/godot.zip

RUN set -eux; \
    echo "${GODOT_SHA256}  /tmp/godot.zip" | sha256sum -c -; \
    unzip /tmp/godot.zip -d /tmp/godot; \
    install -m 0755 "/tmp/godot/Godot_v${GODOT_VERSION}-stable_linux.x86_64" /usr/local/bin/godot; \
    rm -rf /tmp/godot /tmp/godot.zip

WORKDIR /app
RUN chown godot:godot /app
COPY --chown=godot:godot project/ /app/

USER godot
RUN godot --headless --editor --path /app --quit

EXPOSE 9001
STOPSIGNAL SIGINT

CMD ["godot", "--headless", "--path", "/app", "--", "--server", "--port=9001"]
