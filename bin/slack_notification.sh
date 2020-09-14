#!/bin/sh

if [ "$1" = "success" ]
then
  SLACK_MESSAGE_TITLE="Success"
  SLACK_MESSAGE_COLOR="#2ecc71"
elif [ "$1" = "failure" ]
then
  SLACK_MESSAGE_TITLE="Failure"
  SLACK_MESSAGE_COLOR="#f35a00"
fi

curl -X POST \
  -H 'Content-type: application/json' \
  --data '{
    "attachments": [{
      "title": "'"${SLACK_MESSAGE_TITLE}"'",
      "color": "'"${SLACK_MESSAGE_COLOR}"'",
      "fields": [
        { "title": "Repository", "value": "Backend" },
        { "title": "Revision", "value": "'"${GIT_REVISION}"'" },
      ]
    }]
  }' \
  ${SLACK_WEBHOOK_URL}
