#!/dev/null

if [[ $EUID -eq 0 ]]
then
    echo "Don't be root when running this" >&2
    exit 1
fi

set -ex

VENDOR_NAME=adshares

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/${VENDOR_NAME}}
