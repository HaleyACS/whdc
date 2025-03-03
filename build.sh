#!/bin/sh


# web API Data Collector => whdc
# 

# Configure remote repository to push latest build out.
REPO="repo.shdw.fr:5000"


# === Nothing to edit below this point ================

if [ ! -f .build ]
then
    echo "BUILD=1" > .build
fi

# Get installer data
if [ -f ./00_infosource.cfg ]
then
    source ./00_infosource.cfg
    source ./.build
fi

BUILD_VERSION=${RELEASE}b${BUILD}
let BUILDSTORE=($BUILD + 1)
echo "BUILD=$BUILDSTORE" > .build

PNAME=${FileBase}
echo "Removing dangling docker images"
docker rmi $(docker images -f "dangling=true" -q)

echo
echo "*** If you want to apply OS Update, don't use the cache."
echo -n ">>> Use cache for build [y/n]?: "
read Cache
if [ "$Cache" == "y" ]
  then
    echo "Building $PNAME image (cache)"
    docker build -t mertin/${PNAME} .
else
    echo "Building $PNAME image (nocache)"
    docker build --no-cache -t mertin/${PNAME} .
fi


echo "Tagging: mertin/${PNAME}:latest mertin/${PNAME}:${BUILD_VERSION}"
docker tag mertin/${PNAME}:latest mertin/${PNAME}:${BUILD_VERSION}

echo
echo -n ">>> Push to $REPO [y/n]?: "
read Push
if [ "${Push}" == "y" ]
  then
      docker tag mertin/${PNAME}:latest ${REPO}/mertin/${PNAME}:k8s-${BUILD_VERSION}
      docker tag mertin/${PNAME}:latest ${REPO}/mertin/${PNAME}:latest
      # Push out latest
      docker push ${REPO}/mertin/${PNAME}:latest
fi
