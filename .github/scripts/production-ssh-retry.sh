#!/usr/bin/env bash

# Shared by production drills. The hosting provider occasionally drops a new
# SSH handshake before authentication, so every idempotent drill operation gets
# the same bounded retry policy.
production_ssh_init() {
  SSH=(ssh -p "$SSH_PORT" -o BatchMode=yes -o ConnectTimeout=20 -o ConnectionAttempts=3 -o ServerAliveInterval=15 -o ServerAliveCountMax=3 -o StrictHostKeyChecking=yes -o UserKnownHostsFile="$HOME/.ssh/known_hosts" "$SSH_USER@$SSH_HOST")
  SCP=(scp -P "$SSH_PORT" -o BatchMode=yes -o ConnectTimeout=20 -o ConnectionAttempts=3 -o ServerAliveInterval=15 -o ServerAliveCountMax=3 -o StrictHostKeyChecking=yes -o UserKnownHostsFile="$HOME/.ssh/known_hosts")
}

production_retry() {
  local attempt
  for attempt in 1 2 3; do
    if "$@"; then return 0; fi
    if [ "$attempt" -lt 3 ]; then sleep $((attempt * 5)); fi
  done
  return 1
}

production_retry_capture() {
  local attempt output
  for attempt in 1 2 3; do
    if output=$("$@"); then printf '%s\n' "$output"; return 0; fi
    if [ "$attempt" -lt 3 ]; then sleep $((attempt * 5)); fi
  done
  return 1
}
