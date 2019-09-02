FROM registry.metager.de/open-source/proxy/php-fpm:latest

COPY . /html

WORKDIR /html
EXPOSE 80

CMD /html/service-configs/start.sh
