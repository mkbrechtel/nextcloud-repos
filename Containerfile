FROM docker.io/nextcloud:32 as server-base

# Install additional dependencies for the repos app (git, git-annex, datalad)
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    git \
    git-annex \
    python3-pip \
    python3-setuptools \
    && rm -rf /var/lib/apt/lists/*

# Install datalad (optional, comment out if not needed)
RUN pip3 install --no-cache-dir datalad --break-system-packages

# Pre-initialize Nextcloud during build
RUN NEXTCLOUD_ADMIN_USER=admin NEXTCLOUD_ADMIN_PASSWORD=admin SQLITE_DATABASE=sqlite NEXTCLOUD_UPDATE=1 /entrypoint.sh echo "Initialization complete"
RUN php occ config:system:set debug --value=true
RUN php occ app:disable firstrunwizard
RUN php occ config:system:set trusted_domains 1 --value=localhost
RUN php occ config:system:set trusted_domains 2 --value=127.0.0.1

# Set the working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Use the default Nextcloud entrypoint
CMD ["apache2-foreground"]

FROM server-base as dev-env

# Ensure custom_apps directory exists with correct permissions
RUN mkdir -p /var/www/html/custom_apps && \
    chown -R www-data:www-data /var/www/html/custom_apps

# Copy the app into the Nextcloud apps directory
COPY --chown=www-data:www-data . /var/www/html/custom_apps/repos

# Enable the repos app
RUN php occ app:enable repos

FROM dev-env as test-env

# Install Python and Playwright system dependencies
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    # Playwright browser dependencies for Debian
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

# Copy test requirements and install
COPY tests/browser/requirements.txt /tmp/test-requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /tmp/test-requirements.txt

# Install Playwright browsers
RUN playwright install chromium

# Set environment variables for Playwright
ENV PW_TEST_HTML_REPORT_OPEN=never

# Set working directory for tests
WORKDIR /var/www/html/custom_apps/repos
