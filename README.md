# laravel-session-migrate

1. Prepare tables
- php artisan session:table
- php artisan migrate


2. Create Artisan Console command
In the App\Console\Commands directory, create a SessionMigrate.php file with the following content:


3. Run the migration script
- php artisan migrate:sessions


4. Clear the Laravel config cache
- php artisan config:cache


5. Done.