#!/bin/sh
echo "Waiting for database..."
until php artisan db:show > /dev/null 2>&1; do
    sleep 2
done
echo "Database ready."
exec php artisan queue:work --sleep=3 --tries=3 --timeout=60 --max-jobs=500
