#!/bin/sh

CONFIG="application/config/telegram.php"

BOT_UID=`grep telegram_bot_id ${CONFIG} | cut -d '=' -f2 | awk '{print $1}' | tr -d "'" | tr -d ';'`
BOT_TOKEN=`grep telegram_bot_key ${CONFIG} | cut -d '=' -f2 | awk '{print $1}' | tr -d "'" | tr -d ';'`

TOKEN="${BOT_UID}:${BOT_TOKEN}"
KEY="telegram.oak.pending"

while :
do
	DATA=$(curl -s "https://api.telegram.org/bot$TOKEN/getWebhookInfo")
	PENDING=$(echo $DATA | jq '.result.pending_update_count')
	(echo $DATA | jq -c .) >> log.error
	#echo "$KEY $PENDING "$(date +%s) | ncat localhost 2003
	echo "["$(date +%T)"] $PENDING"
	sleep 7
done
