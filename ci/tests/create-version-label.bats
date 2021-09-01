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

create_git_mock() {
  export tag="$1"

  function git() {
    if [ "$1" = "tag" ]; then
      echo "$tag"
    fi
  }

  export -f git
}

@test "$script_name: minor version is incremented" {
  current_tag="0.1"
  expected_version_label="0.2"

  create_git_mock "$current_tag"

  run main

  assert_success
  assert_output "$expected_version_label"
}

@test "$script_name: major version is not incremented when minor version reaches roll-over boundary" {
  current_tag="1.9"
  expected_version_label="1.10"

  create_git_mock "$current_tag"

  run main

  assert_success
  assert_output "$expected_version_label"
}
