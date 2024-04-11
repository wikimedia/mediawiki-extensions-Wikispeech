#!/bin/bash

mkdir volumes
mkdir volumes/wikispeech-server_tmp
chmod a+rwx volumes/wikispeech-server_tmp

docker compose up --force-recreate
