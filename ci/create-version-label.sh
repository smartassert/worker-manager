#!/usr/bin/env bash

git fetch --tags --quiet
LATEST_TAG=$(git tag | tail -1)
MAJOR_VERSION=$(echo "$LATEST_TAG" | cut -d'.' -f1)
MINOR_VERSION=$(echo "$LATEST_TAG" | cut -d'.' -f2)
NEXT_MINOR_VERSION=$((MINOR_VERSION+1))
VERSION="${MAJOR_VERSION}.${NEXT_MINOR_VERSION}"

echo "$VERSION"
