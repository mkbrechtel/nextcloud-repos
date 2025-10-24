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
