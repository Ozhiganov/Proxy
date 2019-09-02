#!/bin/bash

# Set the redis hostname
sed -i "s/REDIS_HOST=/REDIS_HOST=${REDIS_SERVICE_NAME}.${POD_NAMESPACE}/g" .env

/etc/init.d/php7.3-fpm start
/etc/init.d/nginx start

tail -f /dev/null