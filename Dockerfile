FROM nginx

RUN apt -y update
RUN apt -y install php-fpm
RUN apt -y install ca-certificates
RUN apt -y install zip
RUN apt -y install php7.3-mbstring
RUN apt -y install php7.3-dom
RUN apt -y install php7.3-zip
RUN apt -y install php7.3-curl

COPY /service-configs/nginx/default.conf /etc/nginx/conf.d/default.conf

RUN sed -i 's/listen.owner = www-data/listen.owner = nginx/g' /etc/php/7.3/fpm/pool.d/www.conf
RUN sed -i 's/listen.group = www-data/listen.group = nginx/g' /etc/php/7.3/fpm/pool.d/www.conf
RUN sed -i 's/user = www-data/user = nginx/g' /etc/php/7.3/fpm/pool.d/www.conf
RUN sed -i 's/group = www-data/group = nginx/g' /etc/php/7.3/fpm/pool.d/www.conf
RUN sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g' /etc/php/7.3/fpm/php.ini

WORKDIR /html

CMD /html/service-configs/start.sh
