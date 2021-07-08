#!/usr/bin/env bash

if [ "true" == "$AUTO_GENERATE" ]; then
  git fetch --tags --quiet
  LATEST_TAG=$(git tag | tail -1)
  MAJOR_VERSION=$(echo $LATEST_TAG | cut -d'.' -f1)
  MINOR_VERSION=$(echo $LATEST_TAG | cut -d'.' -f2)
  NEXT_MINOR_VERSION=$((MINOR_VERSION+1))
  VERSION="${MAJOR_VERSION}.${NEXT_MINOR_VERSION}"
else
  if [ ! -z "$RELEASE_TAG_NAME" ]; then
    VERSION=$RELEASE_TAG_NAME
  else
    VERSION="master"
  fi
fi

echo $VERSION
