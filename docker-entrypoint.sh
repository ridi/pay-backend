#!/bin/bash

eval $(aws ssm get-parameters-by-path --region ap-northeast-2 --with-decryption --path /ridi-pay | jq -r '.Parameters| .[] | "export " + .Name + "=\"" + .Value + "\""' | sed -e "s#/ridi-pay/##")
apachectl -D FOREGROUND
