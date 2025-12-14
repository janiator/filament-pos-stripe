$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

npm ci || npm install && npm run build

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
$FORGE_PHP artisan horizon:terminate
