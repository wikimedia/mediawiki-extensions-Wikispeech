#!/usr/bin/env bash

echo "Waiting for all other services to start..."

/srv/compose/wait-for-it.sh -h mariadb -p 3306 -t 120

echo "Starting Pronlex..."

DIR=`pwd`

export GOROOT=${DIR}/go
export GOPATH=${DIR}/goProjects
export PATH=${GOPATH}/bin:${GOROOT}/bin:${PATH}

cd pronlex

/bin/bash scripts/start_server.sh -a ${DIR}/appdir -e sqlite -p 8787
