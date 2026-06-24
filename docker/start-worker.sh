#!/bin/sh
echo "Waiting for database..."
until php artisan db:show > /dev/null 2>&1; do
    sleep 2
done
echo "Database ready."
exec php artisan queue:work \
    --queue=sync,notifications \
    --sleep=3 \
    --tries=3 \
    --timeout=90 \
    --max-jobs=500 \
    --max-time=3600
