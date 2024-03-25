#!/usr/bin/env bash

echo "Starting Pronlex..."

DIR=`pwd`

cd pronlex

if [[ -z "${PRONLEX_MARIADB_URI}" ]]; then
  /bin/bash scripts/start_server.sh -a ${DIR}/appdir -e sqlite -p 8787 -r lexserver
else
  echo "Waiting for all other services to start..."
  /srv/compose/wait-for-it.sh -h mariadb -p 3306 -t 120

  /bin/bash scripts/start_server.sh -a ${DIR}/appdir -e mariadb -l "${PRONLEX_MARIADB_URI}" -p 8787 -r lexserver
fi
