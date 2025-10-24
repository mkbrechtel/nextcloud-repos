FROM docker.io/nextcloud:32 as server-base

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    git \
    git-annex \
    python3-pip \
    python3-setuptools \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN pip3 install --no-cache-dir datalad --break-system-packages

RUN NEXTCLOUD_ADMIN_USER=admin NEXTCLOUD_ADMIN_PASSWORD=admin SQLITE_DATABASE=sqlite NEXTCLOUD_UPDATE=1 /entrypoint.sh echo "Initialization complete"

RUN php occ config:system:set debug --value=true && \
    php occ app:disable firstrunwizard && \
    php occ config:system:set trusted_domains 1 --value=localhost && \
    php occ config:system:set trusted_domains 2 --value=127.0.0.1

WORKDIR /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]

FROM debian:trixie as test-env

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    python3 \
    curl \
    libnss3 \
    libnspr4 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libdbus-1-3 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libasound2t64 \
    libatspi2.0-0 \
    libxshmfence1 \
    fonts-liberation \
    fonts-noto-color-emoji \
    && rm -rf /var/lib/apt/lists/*

RUN curl -LsSf https://astral.sh/uv/install.sh | sh
ENV PATH="/root/.local/bin:$PATH"

COPY tests/browser/requirements.txt /tmp/test-requirements.txt
RUN UV_SYSTEM_PYTHON=1 uv pip install --system --break-system-packages -r /tmp/test-requirements.txt

RUN playwright install chromium

ENV PW_TEST_HTML_REPORT_OPEN=never
WORKDIR /var/www/html/custom_apps/repos

FROM node:20-bookworm-slim as app-build

RUN apt-get update && \
    apt-get install -y --no-install-recommends make && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm install

COPY rollup.config.js babel.config.cjs .babelrc.cjs tsconfig.json .eslintrc.js ./
COPY src ./src
COPY img ./img

RUN npm run dev

FROM server-base as dev-env

RUN mkdir -p /var/www/html/custom_apps && \
    chown -R www-data:www-data /var/www/html/custom_apps

COPY --chown=www-data:www-data . /var/www/html/custom_apps/repos

COPY --from=app-build --chown=www-data:www-data /build/js /var/www/html/custom_apps/repos/js

RUN php occ app:enable repos
