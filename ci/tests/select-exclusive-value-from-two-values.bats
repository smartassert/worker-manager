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
  VALUE1="repository_dispatch_image" \
  VALUE2="workflow_dispatch_image" \
  run main

  assert_failure "1"
}

@test "$script_name: VALUE1 set outputs VALUE1" {
  VALUE1="repository_dispatch_image" \
  run main

  assert_success
  assert_output "repository_dispatch_image"
}

@test "$script_name: VALUE2 set outputs VALUE2" {
  VALUE2="workflow_dispatch_image" \
  run main

  assert_success
  assert_output "workflow_dispatch_image"
}
