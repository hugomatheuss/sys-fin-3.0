#!/bin/sh
set -eu

mkdir -p vendor storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R app:app vendor storage bootstrap/cache

exec gosu app "$@"
