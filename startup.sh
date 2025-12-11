#!/bin/bash

echo "Starting PHP application..."

# Copy custom nginx config if exists
if [ -f /home/site/wwwroot/nginx.conf ]; then
    cp /home/site/wwwroot/nginx.conf /etc/nginx/sites-available/default
    echo "Custom nginx config copied"
fi

# Reload nginx
nginx -s reload

echo "Startup complete"
