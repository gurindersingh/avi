#!/bin/bash

cd /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}

zsh ./{{ $currentRelease }}.sh > ./output.log 2>&1
