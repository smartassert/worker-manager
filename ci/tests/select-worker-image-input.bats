#!/usr/bin/env bats

script_name=$(basename "$BATS_TEST_FILENAME" | sed 's/bats/sh/g')
export script_name

setup() {
  load 'node_modules/bats-support/load'
  load 'node_modules/bats-assert/load'
}

main() {
  bash "${BATS_TEST_DIRNAME}/../scripts/$script_name"
}

@test "$script_name: no arguments set fails" {
  run main

  assert_failure "1"
}

@test "$script_name: both arguments set fails" {
  REPOSITORY_DISPATCH_WORKER_IMAGE="repository_dispatch_image" \
  WORKFLOW_DISPATCH_WORKER_IMAGE="workflow_dispatch_image" \
  run main

  assert_failure "1"
}

@test "$script_name: REPOSITORY_DISPATCH_WORKER_IMAGE set outputs REPOSITORY_DISPATCH_WORKER_IMAGE" {
  REPOSITORY_DISPATCH_WORKER_IMAGE="repository_dispatch_image" \
  run main

  assert_success
  assert_output "repository_dispatch_image"
}

@test "$script_name: WORKFLOW_DISPATCH_WORKER_IMAGE set outputs WORKFLOW_DISPATCH_WORKER_IMAGE" {
  WORKFLOW_DISPATCH_WORKER_IMAGE="workflow_dispatch_image" \
  run main

  assert_success
  assert_output "workflow_dispatch_image"
}
