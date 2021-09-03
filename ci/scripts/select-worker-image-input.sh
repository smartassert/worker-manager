#!/usr/bin/env bash

if
  ([ -z "$REPOSITORY_DISPATCH_WORKER_IMAGE" ] && [ -z "$WORKFLOW_DISPATCH_WORKER_IMAGE" ]) ||
  ([ -n "$REPOSITORY_DISPATCH_WORKER_IMAGE" ] && [ -n "$WORKFLOW_DISPATCH_WORKER_IMAGE" ])
then
  exit 1
fi

if [ -n "$REPOSITORY_DISPATCH_WORKER_IMAGE" ]; then
  echo "$REPOSITORY_DISPATCH_WORKER_IMAGE"
fi

if [ -n "$WORKFLOW_DISPATCH_WORKER_IMAGE" ]; then
  echo "$WORKFLOW_DISPATCH_WORKER_IMAGE"
fi
