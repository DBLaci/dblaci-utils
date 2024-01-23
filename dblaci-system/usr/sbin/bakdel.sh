#!/bin/sh
# as btrfs su de command doesn't support multiple removes, this script allows you to remove multiple subvolumes at once.
for i in $*
do
    btrfs subvolume delete $i
done
