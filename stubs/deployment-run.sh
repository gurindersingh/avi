#!/bin/bash

cd /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}

zsh ./deployment.sh > ./output.log 2>&1
