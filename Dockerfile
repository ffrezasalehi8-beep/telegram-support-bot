FROM php:8.3-cli-alpine

WORKDIR /app

COPY src/ /app/src/
COPY public/ /app/public/
COPY bin/ /app/bin/
COPY docker-entrypoint.sh /app/docker-entrypoint.sh

RUN chmod +x /app/docker-entrypoint.sh

ENV PORT=8080

EXPOSE 8080

ENTRYPOINT ["/bin/sh", "/app/docker-entrypoint.sh"]
