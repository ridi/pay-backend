#!/bin/bash

eval $(aws ssm get-parameters-by-path --region ap-northeast-2 --with-decryption --path /ridi-pay/$APP_ENV | jq -r '.Parameters[] | "export " + (.Name | gsub("'/ridi-pay/$APP_ENV/'"; "")) + "=\"" + (.Value | gsub("\n"; "\\n")) + "\""')
apachectl -D FOREGROUND
