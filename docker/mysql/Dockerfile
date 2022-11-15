FROM mysql:5.7.34
MAINTAINER fresh@idealogica.me

ENV TZ=UTC

# Config

COPY ./docker/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf

# Shell aliases

ENV ENV="/root/.ashrc"
RUN echo "alias ll='ls -al'" > "$ENV"

CMD ["mysqld"]
