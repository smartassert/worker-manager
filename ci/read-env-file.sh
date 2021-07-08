#!/usr/bin/env bash

while read line
do
  if [ "" != "$line" ]; then
    key=$(echo $line | cut -d'=' -f1)
    value=$(echo $line | cut -d'=' -f2)
    echo "::set-output name=$key::$value"
  fi
done < $ENV_FILE_PATH
