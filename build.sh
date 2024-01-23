#!/bin/sh
PACKAGE="dblaci-system"

# The first argument is the version from the Git tag
VERSION=$1

# Update the Version field in the control file
sed -i "s/^Version:.*/Version: $VERSION/" $PACKAGE/DEBIAN/control

mkdir -p builds

# Build the package with the version in the filename
dpkg-deb --build $PACKAGE builds/${PACKAGE}_${VERSION}.deb
