#!/usr/bin/env bash

if [ ! -z "$DEPLOY_IMAGE" ]; then
  echo $DEPLOY_IMAGE
else
  echo $SOURCE_IMAGE
fi
