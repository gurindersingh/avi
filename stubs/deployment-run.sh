#!/bin/bash

cd /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}

#zsh ./deployment.sh > ./output.log 2>&1
#zsh ./deployment.sh > ./output.log
zsh ./deployment.sh 2>&1 | tee ./output.log
