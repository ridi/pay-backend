#!/bin/sh

if [ "$1" = "success" ]
then
  SLACK_MESSAGE_TITLE="Success"
  SLACK_MESSAGE_COLOR="#2ecc71"
elif [ "$1" = "fail" ]
then
  SLACK_MESSAGE_TITLE="Fail"
  SLACK_MESSAGE_COLOR="#f35a00"
fi

curl -X POST \
  -H 'Content-type: application/json' \
  --data '{
    "attachments": [{
      "title": "'"${SLACK_MESSAGE_TITLE}"'",
      "text": "'"${TRAVIS_COMMIT_MESSAGE}"'",
      "color": "'"${SLACK_MESSAGE_COLOR}"'",
      "fields": [
        { "title": "Repository", "value": "Backend" },
        { "title": "Revision", "value": "'"${TRAVIS_COMMIT}"'" },
      ]
    }]
  }' \
  ${SLACK_WEBHOOK_URL}
