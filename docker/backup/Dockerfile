FROM postgres:16-alpine

RUN apk add --no-cache tzdata

COPY backup-db.sh /usr/local/bin/backup-db.sh
COPY entrypoint.sh /usr/local/bin/backup-entrypoint.sh

RUN chmod +x /usr/local/bin/backup-db.sh /usr/local/bin/backup-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/backup-entrypoint.sh"]
