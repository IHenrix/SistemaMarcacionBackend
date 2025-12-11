#!/bin/bash

echo "Starting PHP application..."

# Copy custom nginx config
if [ -f /home/site/wwwroot/default ]; then
    cp /home/site/wwwroot/default /etc/nginx/sites-available/default
    echo "Custom nginx config copied"
    service nginx reload
fi

echo "Startup complete"
