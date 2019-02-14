#!/dev/null

if [[ ${_FUNCTIONS_FILE_WAS_LOADED:-0} -eq 1 ]]
then
    echo "Functions file was already loaded" &>2
    exit 127
fi

if [[ $EUID -eq 0 ]]
then
    echo "Don't be root when running $0" >&2
    exit 1
fi

VENDOR_NAME=${VENDOR_NAME:-adshares}

if [[ "$VENDOR_NAME" != `id --user --name` ]]
then
    echo "You need to be $VENDOR_NAME to run $0" >&2
    exit 1
fi

set -ex

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/${VENDOR_NAME}}

_FUNCTIONS_FILE_WAS_LOADED=1
