#!/usr/bin/env bash

if
  { [ -z "$VALUE1" ] && [ -z "$VALUE2" ]; } ||
  { [ -n "$VALUE1" ] && [ -n "$VALUE2" ]; }
then
  exit 1
fi

if [ -n "$VALUE1" ]; then
  echo "$VALUE1"
fi

if [ -n "$VALUE2" ]; then
  echo "$VALUE2"
fi
