#!/bin/bash

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <command> [arguments...]"
    exit 1
fi

COMMAND=$1
shift
ARGS="$@"

$COMMAND $ARGS
