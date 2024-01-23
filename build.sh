#!/bin/sh
PACKAGE="dblaci-system"

# Extract the version from the control file
VERSION=$(grep 'Version:' $PACKAGE/DEBIAN/control | cut -d ' ' -f 2)

mkdir -p builds

# Build the package with the version in the filename
dpkg-deb --build $PACKAGE builds/${PACKAGE}_${VERSION}.deb
