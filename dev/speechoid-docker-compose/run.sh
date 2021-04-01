#!/bin/bash

mkdir volumes
mkdir volumes/wikispeech_mockup_tmp
chmod a+rwx volumes/wikispeech_mockup_tmp

docker-compose up --force-recreate
