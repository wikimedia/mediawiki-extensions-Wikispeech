#!/usr/bin/env bash

echo "Waiting for all other services to start..."
/srv/compose/wait-for-it.sh -h mishkal -p 8080 -t 60

echo "Configuring HAProxy"
cat > /srv/haproxy-generated.cfg <<EOF
global
	daemon
	maxconn ${HAPROXY_QUEUE_SIZE:-100}

defaults
	mode tcp
	timeout connect ${HAPROXY_TIMEOUT_CONNECT:-60s}
	timeout client ${HAPROXY_TIMEOUT_CLIENT:-60s}
	timeout server ${HAPROXY_TIMEOUT_SERVER:-60s}

frontend frontend_1
	bind *:${HAPROXY_MARY_TTS_FRONTEND_PORT:-8080}
	default_backend backend_1

backend backend_1
	server server_1 127.0.0.1:${HAPROXY_MARY_TTS_BACKEND_PORT:-59125} maxconn ${HAPROXY_MARY_TTS_BACKEND_MAXIMUM_CONCURRENT_CONNECTIONS:-1}

frontend stats
	mode http
	bind *:8404
	stats enable
	stats uri /stats
	stats refresh ${HAPROXY_STATS_FRONTEND_REFRESH_RATE:-4s}
	stats admin if TRUE
EOF

echo "Starting HAProxy"
/usr/sbin/haproxy -f /srv/haproxy-generated.cfg

echo "Starting Mary TTS"
export GRADLE_USER_HOME=/srv/gradle_user_home
cd /srv/mary-tts/build/install/mary-tts/
./bin/marytts-server
