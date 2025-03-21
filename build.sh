#!/bin/bash


# web API Data Collector => whdc
# 

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

echo "  > $Subject - $FileBase $BUILD_VERSION" > release.txt
echo "  > By $Author" >> release.txt

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
