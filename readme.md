MetaGer - Proxy

## Installation with docker
* `docker run -it --rm --name metager-proxy-npm-install -v "$PWD":/usr/src/app -w /usr/src/app node:8 npm install && npm run prod`
* `docker run --rm -v $(pwd):/app composer/composer:latest install`