#!/bin/bash

echo "Waiting for all other services to start..."

/srv/compose/wait-for-it.sh -h mishkal -p 8080 -t 60
/srv/compose/wait-for-it.sh -h mary-tts -p 59125 -t 60
/srv/compose/wait-for-it.sh -h mary-tts -p 8080 -t 60
/srv/compose/wait-for-it.sh -h pronlex -p 8787 -t 60
/srv/compose/wait-for-it.sh -h symbolset -p 8771 -t 60

echo "Starting Wikispeech mockup."
cd /srv/wikispeech-mockup
python3 bin/wikispeech /srv/compose/mockup.conf
